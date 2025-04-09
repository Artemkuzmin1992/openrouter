<?php
/**
 * Класс API OpenRouter
 * 
 * Обрабатывает связь с API OpenRouter для генерации текста с использованием модели AI
 */

if (!defined('ABSPATH')) {
    exit; // Выход при прямом доступе
}

class DeepSeek_OpenRouter_API {
    
    /**
     * Базовый URL API
     */
    private $api_base_url = 'https://openrouter.ai/api/v1';
    
    /**
     * ID модели OpenRouter
     * Изменено с DeepSeek на Claude 3 Haiku, так как она работает с настройками конфиденциальности по умолчанию
     */
    private $model_id = 'anthropic/claude-3-haiku:beta';
    
    /**
     * Ключ API
     */
    private $api_key = '';
    
    /**
     * Использовать ли локальную конечную точку API
     */
    private $use_local_api = false;
    
    /**
     * URL локального API
     */
    private $local_api_url = '';
    
    /**
     * Конструктор
     */
    public function __construct() {
        $settings = get_option('deepseek_settings', array());
        $this->api_key = isset($settings['openrouter_api_key']) ? $settings['openrouter_api_key'] : '';
        // Принудительно отключаем локальный API, чтобы работать только с прямым API
        $this->use_local_api = false;
        $this->local_api_url = isset($settings['local_api_url']) ? $settings['local_api_url'] : 'http://localhost:5000/api/generate';
    }
    
    /**
     * Переопределение настроек для временного использования (для тестирования)
     * 
     * @param bool $use_local_api Использовать ли локальный API
     * @param string $api_key Ключ API
     * @param string $local_api_url URL локального API
     */
    public function override_settings($use_local_api, $api_key, $local_api_url) {
        // Принудительно отключаем локальный API, чтобы работать только с прямым API
        $this->use_local_api = false;
        $this->api_key = $api_key;
        
        if (!empty($local_api_url)) {
            $this->local_api_url = $local_api_url;
        }
    }
    
    // Функция проверки API удалена для упрощения интерфейса
    
    /**
     * Проверка, является ли текст ID пользователя OpenRouter
     * 
     * @param string $text Текст для проверки
     * @return bool True, если текст является ID пользователя
     */
    private function is_openrouter_user_id($text) {
        if (!$text || !is_string($text)) return false;
        return preg_match('/^user_[a-zA-Z0-9]{20,}$/', $text) || 
               (strpos($text, 'user_') !== false && strlen($text) < 40);
    }
    
    /**
     * Генерация текста с использованием модели AI
     * 
     * @param string $prompt Запрос для генерации текста
     * @param int $max_tokens Максимальное количество токенов для генерации
     * @return string|WP_Error Сгенерированный текст или ошибка
     */
    public function generate_text($prompt, $max_tokens = 500) {
        if (empty($this->api_key)) {
            return new WP_Error('invalid_api_key', __('API-ключ не настроен', 'deepseek-product-descriptions'));
        }
        
        // Добавляем небольшую задержку перед каждым запросом, чтобы не перегружать API
        // Это поможет избежать ошибок, связанных с ограничениями запросов (rate limits)
        usleep(500000); // 500 мс задержка
        
        // Проверяем, должны ли мы использовать локальную конечную точку API
        if ($this->use_local_api) {
            return $this->generate_text_via_local_api($prompt, $max_tokens);
        } else {
            // Делаем несколько попыток в случае ошибки
            $max_attempts = 5; // Увеличиваем до 5 попыток для надежности
            $attempt = 1;
            
            while ($attempt <= $max_attempts) {
                $result = $this->generate_text_via_direct_api($prompt, $max_tokens);
                
                // Если результат является строкой, проверяем, не является ли она ID пользователя
                if (is_string($result) && $this->is_openrouter_user_id($result)) {
                    error_log("Попытка $attempt: API вернул ID пользователя: $result. Повторяем запрос...");
                    // Увеличиваем задержку с каждой попыткой
                    usleep(1000000 * $attempt); // От 1 до 5 секунд задержки
                    $attempt++;
                    continue;
                }
                
                // Если запрос успешен или ошибка не связана с форматом ответа или ID пользователя,
                // возвращаем результат
                if (!is_wp_error($result) || 
                    (is_wp_error($result) && 
                     strpos($result->get_error_message(), 'Некорректный формат') === false &&
                     strpos($result->get_error_message(), 'идентификатор пользователя') === false)) {
                    return $result;
                }
                
                // Увеличиваем задержку с каждой попыткой
                usleep(1000000 * $attempt); // От 1 до 5 секунд задержки
                $attempt++;
                
                error_log("Попытка $attempt из $max_attempts для запроса API");
            }
            
            // Если все попытки не удались, но последний результат - строка (не ошибка),
            // возвращаем его, даже если это может быть ID пользователя
            if (is_string($result)) {
                return $result;
            }
            
            // Возвращаем последний результат или общую ошибку
            return is_wp_error($result) ? $result : 
                new WP_Error('api_failed', __('Не удалось получить корректный ответ после нескольких попыток', 'deepseek-product-descriptions'));
        }
    }
    
    /**
     * Генерация текста с использованием локальной конечной точки API
     * 
     * @param string $prompt Запрос для генерации текста
     * @param int $max_tokens Максимальное количество токенов для генерации
     * @return string|WP_Error Сгенерированный текст или ошибка
     */
    private function generate_text_via_local_api($prompt, $max_tokens = 500) {
        $data = array(
            'api_key' => $this->api_key,
            'prompt' => $prompt,
            'max_tokens' => $max_tokens
        );
        
        $response = wp_remote_post(
            $this->local_api_url,
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($data),
                'timeout' => 30,
                'data_format' => 'body',
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error_message = sprintf(
                __('Ошибка локального API (Код ответа: %d)', 'deepseek-product-descriptions'),
                $status_code
            );
            return new WP_Error('api_error', $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['description'])) {
            return new WP_Error('invalid_response', __('Некорректный ответ от локального API', 'deepseek-product-descriptions'));
        }
        
        return $data['description'];
    }
    
    /**
     * Генерация текста с непосредственным использованием API OpenRouter
     * 
     * @param string $prompt Запрос для генерации текста
     * @param int $max_tokens Максимальное количество токенов для генерации
     * @return string|WP_Error Сгенерированный текст или ошибка
     */
    private function generate_text_via_direct_api($prompt, $max_tokens = 500) {
        // Проверка на пустой API-ключ
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key', 
                __('API-ключ не настроен. Пожалуйста, добавьте ваш API-ключ OpenRouter в настройках плагина или включите опцию "Использовать локальный API-сервис" с demo_key.', 'deepseek-product-descriptions')
            );
        }
        
        $data = array(
            'model' => $this->model_id,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Вы профессиональный копирайтер для электронной коммерции. Ваша задача - создавать привлекательные, точные и убедительные описания товаров. Сосредоточьтесь на преимуществах, характеристиках и ценности. Используйте активный залог, будьте лаконичны и оптимизируйте для SEO. Всегда поддерживайте профессиональный тон, соответствующий голосу бренда. Пишите на русском языке.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
            'top_p' => 0.9,
        );
        
        $response = wp_remote_post(
            $this->api_base_url . '/chat/completions',
            array(
                'headers' => $this->get_headers(),
                'body' => json_encode($data),
                'timeout' => 30,
                'data_format' => 'body',
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Проверка на прямое возвращение строки с user_id
        if (is_string($body) && strpos($body, 'user_') === 0) {
            error_log('API вернул строку с ID пользователя: ' . $body);
            return new WP_Error('user_id_response', 
                __('API вернул идентификатор пользователя. Повторяем запрос...', 'deepseek-product-descriptions'));
        }
        
        if ($status_code !== 200) {
            // Обработка конкретных кодов ошибок
            $error_message = '';
            
            switch ($status_code) {
                case 401:
                    $error_message = __('Ошибка аутентификации. Пожалуйста, проверьте ваш API-ключ OpenRouter в настройках плагина или попробуйте использовать демо-режим.', 'deepseek-product-descriptions');
                    break;
                case 403:
                    $error_message = __('Доступ запрещен. Ваш API-ключ может не иметь разрешения на использование этой модели.', 'deepseek-product-descriptions');
                    break;
                case 429:
                    $error_message = __('Превышен лимит запросов. Пожалуйста, попробуйте позже или обновите ваш план OpenRouter.', 'deepseek-product-descriptions');
                    break;
                default:
                    $error_details = !empty($data['error']['message']) ? $data['error']['message'] : '';
                    $error_message = sprintf(
                        __('Ошибка API (Код ответа: %d). %s', 'deepseek-product-descriptions'),
                        $status_code,
                        $error_details
                    );
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        // Добавляем подробный лог для отладки
        if (empty($data)) {
            return new WP_Error('empty_response', __('Пустой ответ от API', 'deepseek-product-descriptions'));
        }
        
        // Добавляем журналирование для более глубокой отладки
        error_log('OpenRouter API ответ: ' . substr(print_r($data, true), 0, 1000)); // Ограничиваем длину лога
        
        // Проверяем различные форматы ответа, которые могут вернуться от API
        if (isset($data['choices']) && is_array($data['choices']) && !empty($data['choices'])) {
            // Извлекаем контент из разных форматов ответа
            $content = null;
            
            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
            } elseif (isset($data['choices'][0]['text'])) {
                $content = $data['choices'][0]['text'];
            } elseif (isset($data['choices'][0]['content'])) {
                $content = $data['choices'][0]['content'];
            }
            
            // Проверяем, не является ли контент ID пользователя
            if ($content !== null) {
                if ($this->is_openrouter_user_id($content)) {
                    error_log('API вернул ID пользователя в поле контента: ' . $content);
                    return new WP_Error('user_id_response', 
                        __('API вернул идентификатор пользователя вместо содержимого. Повторяем запрос...', 'deepseek-product-descriptions'));
                }
                return $content;
            }
        }
        
        // Проверяем альтернативные форматы ответа
        $possible_content_fields = [
            'content', 'response', 'message', 'output', 'result', 'text', 'generated_text'
        ];
        
        foreach ($possible_content_fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                // Проверяем, не является ли контент ID пользователя
                if ($this->is_openrouter_user_id($data[$field])) {
                    error_log("API вернул ID пользователя в поле {$field}: " . $data[$field]);
                    return new WP_Error('user_id_response', 
                        __('API вернул идентификатор пользователя вместо содержимого. Повторяем запрос...', 'deepseek-product-descriptions'));
                }
                return $data[$field];
            }
        }
        
        // Если API вернул какие-то данные, попробуем использовать их в аварийном режиме
        if (is_array($data) && !empty($data)) {
            // Перебираем все ключи первого уровня в поисках строкового значения
            foreach ($data as $key => $value) {
                if (is_string($value) && strlen($value) > 10) {
                    // Проверяем, не является ли контент ID пользователя
                    if ($this->is_openrouter_user_id($value)) {
                        error_log("API вернул ID пользователя в поле {$key}: " . $value);
                        return new WP_Error('user_id_response', 
                            __('API вернул идентификатор пользователя вместо содержимого. Повторяем запрос...', 'deepseek-product-descriptions'));
                    }
                    
                    // Нашли строку достаточной длины, используем её
                    return $value;
                } elseif (is_array($value) && isset($value[0]) && is_string($value[0]) && strlen($value[0]) > 10) {
                    // Проверяем, не является ли контент ID пользователя
                    if ($this->is_openrouter_user_id($value[0])) {
                        error_log("API вернул ID пользователя в массиве {$key}: " . $value[0]);
                        return new WP_Error('user_id_response', 
                            __('API вернул идентификатор пользователя вместо содержимого. Повторяем запрос...', 'deepseek-product-descriptions'));
                    }
                    
                    // Проверяем массив строк
                    return $value[0];
                }
            }
        }
        
        // Проверка на "user_" префикс в ID, который иногда возвращается вместо контента
        if (isset($data['id']) && is_string($data['id']) && strpos($data['id'], 'user_') === 0) {
            error_log('API вернул ID пользователя вместо контента: ' . $data['id']);
            return new WP_Error('user_id_response', 
                __('API вернул идентификатор пользователя вместо содержимого. Повторяем запрос...', 'deepseek-product-descriptions'));
        }
        
        // Если не удалось найти содержимое в известных форматах, возвращаем ошибку с дополнительной информацией
        $error_msg = __('Некорректный формат ответа от API. Попробуйте еще раз.', 'deepseek-product-descriptions');
        error_log('Ошибка структуры ответа API: ' . print_r($data, true));
        return new WP_Error('invalid_response', $error_msg);
    }
    
    /**
     * Получение заголовков запроса
     * 
     * @return array Массив заголовков
     */
    private function get_headers() {
        return array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'HTTP_X_TITLE' => 'WooCommerce Генератор описаний товаров',
        );
    }
}