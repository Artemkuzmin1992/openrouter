<?php
/**
 * Класс настроек плагина "Генератор описаний товаров"
 * 
 * Обрабатывает страницу настроек администратора для плагина
 */

if (!defined('ABSPATH')) {
    exit; // Выход при прямом доступе
}

class DeepSeek_Settings {
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Добавление страницы настроек в меню администратора
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Регистрация настроек
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Добавление страницы настроек в меню администратора
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=product',         // Родительский slug
            __('Настройки генератора описаний', 'deepseek-product-descriptions'), // Заголовок страницы
            __('Настройки генератора', 'deepseek-product-descriptions'), // Название в меню
            'manage_options',                     // Необходимые права
            'deepseek-settings',                  // Slug меню
            array($this, 'render_settings_page')  // Функция отображения
        );
    }
    
    /**
     * Регистрация настроек
     */
    public function register_settings() {
        register_setting(
            'deepseek_settings',
            'deepseek_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'deepseek_api_settings',
            __('Настройки API', 'deepseek-product-descriptions'),
            array($this, 'render_api_settings_section'),
            'deepseek-settings'
        );
        
        add_settings_field(
            'openrouter_api_key',
            __('Ключ API OpenRouter', 'deepseek-product-descriptions'),
            array($this, 'render_api_key_field'),
            'deepseek-settings',
            'deepseek_api_settings'
        );
        
        add_settings_field(
            'use_local_api',
            __('Использовать локальный API-сервис', 'deepseek-product-descriptions'),
            array($this, 'render_use_local_api_field'),
            'deepseek-settings',
            'deepseek_api_settings'
        );
        
        add_settings_field(
            'local_api_url',
            __('URL локального API', 'deepseek-product-descriptions'),
            array($this, 'render_local_api_url_field'),
            'deepseek-settings',
            'deepseek_api_settings'
        );
        
        add_settings_section(
            'deepseek_description_settings',
            __('Настройки описаний', 'deepseek-product-descriptions'),
            array($this, 'render_description_settings_section'),
            'deepseek-settings'
        );
        
        add_settings_field(
            'auto_save_descriptions',
            __('Автоматически сохранять сгенерированные описания', 'deepseek-product-descriptions'),
            array($this, 'render_auto_save_field'),
            'deepseek-settings',
            'deepseek_description_settings'
        );
        
        add_settings_field(
            'update_main_description',
            __('Обновлять основное описание товара', 'deepseek-product-descriptions'),
            array($this, 'render_update_main_description_field'),
            'deepseek-settings',
            'deepseek_description_settings'
        );
        
        add_settings_field(
            'update_excerpt',
            __('Обновлять выдержку товара', 'deepseek-product-descriptions'),
            array($this, 'render_update_excerpt_field'),
            'deepseek-settings',
            'deepseek_description_settings'
        );
    }
    
    /**
     * Отображение страницы настроек
     */
    public function render_settings_page() {
        // Проверка прав пользователя
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Показать сообщение об успехе, если настройки были обновлены
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'deepseek_messages',
                'deepseek_message',
                __('Настройки сохранены', 'deepseek-product-descriptions'),
                'updated'
            );
        }
        
        // Получение текущих настроек
        $settings = get_option('deepseek_settings', array());
        
        // Удалили автоматическую проверку API-ключа для упрощения
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('deepseek_messages'); ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('deepseek_settings');
                do_settings_sections('deepseek-settings');
                
                // Раздел тестирования API удален для упрощения
                ?>
                
                <?php
                submit_button(__('Сохранить настройки', 'deepseek-product-descriptions'));
                ?>
            </form>
            
            <div class="deepseek-info-box">
                <h3><?php _e('О плагине "Генератор описаний товаров"', 'deepseek-product-descriptions'); ?></h3>
                <p><?php _e('Этот плагин использует AI-модели через API OpenRouter для генерации привлекательных описаний товаров для вашего магазина WooCommerce.', 'deepseek-product-descriptions'); ?></p>
                <p><?php _e('Для начала работы вам нужен API-ключ OpenRouter. Вы можете получить его, зарегистрировавшись на', 'deepseek-product-descriptions'); ?> <a href="https://openrouter.ai" target="_blank">openrouter.ai</a>.</p>
                
                <h4><?php _e('Инструкция по использованию', 'deepseek-product-descriptions'); ?></h4>
                <ol>
                    <li><?php _e('Введите ваш API-ключ OpenRouter выше', 'deepseek-product-descriptions'); ?></li>
                    <li><?php _e('Перейдите на страницу Товары в WooCommerce', 'deepseek-product-descriptions'); ?></li>
                    <li><?php _e('Используйте колонки плагина для генерации и редактирования описаний', 'deepseek-product-descriptions'); ?></li>
                </ol>

                <h4><?php _e('Демо-режим', 'deepseek-product-descriptions'); ?></h4>
                <p><?php _e('Для тестирования плагина без API-ключа OpenRouter, вы можете использовать Демо-режим:', 'deepseek-product-descriptions'); ?></p>
                <ol>
                    <li><?php _e('Включите опцию "Использовать локальный API-сервис"', 'deepseek-product-descriptions'); ?></li>
                    <li><?php _e('Установите URL локального API на http://localhost:5000/api/generate', 'deepseek-product-descriptions'); ?></li>
                    <li><?php _e('Введите "demo_key" в качестве API-ключа', 'deepseek-product-descriptions'); ?></li>
                    <li><?php _e('Убедитесь, что сервис Flask API запущен (если используется полный пакет)', 'deepseek-product-descriptions'); ?></li>
                </ol>
                <p><?php _e('Примечание: Демо-режим будет генерировать предустановленные описания для распространенных типов товаров.', 'deepseek-product-descriptions'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Отображение раздела настроек API
     */
    public function render_api_settings_section($args) {
        ?>
        <p><?php _e('Настройте параметры подключения к API OpenRouter ниже.', 'deepseek-product-descriptions'); ?></p>
        <?php
    }
    
    /**
     * Отображение раздела настроек описаний
     */
    public function render_description_settings_section($args) {
        ?>
        <p><?php _e('Настройте, как будут обрабатываться сгенерированные описания.', 'deepseek-product-descriptions'); ?></p>
        <?php
    }
    
    /**
     * Отображение поля для API-ключа
     */
    public function render_api_key_field($args) {
        $settings = get_option('deepseek_settings', array());
        $api_key = isset($settings['openrouter_api_key']) ? $settings['openrouter_api_key'] : '';
        
        ?>
        <input type="password" id="openrouter_api_key" name="deepseek_settings[openrouter_api_key]" 
               value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php _e('Введите ваш API-ключ OpenRouter. Вы можете получить его на', 'deepseek-product-descriptions'); ?> 
            <a href="https://openrouter.ai" target="_blank">openrouter.ai</a>
        </p>
        <?php
    }
    
    /**
     * Отображение поля для опции использования локального API
     */
    public function render_use_local_api_field($args) {
        $settings = get_option('deepseek_settings', array());
        $use_local_api = isset($settings['use_local_api']) ? (bool) $settings['use_local_api'] : false;
        
        ?>
        <label for="use_local_api">
            <input type="checkbox" id="use_local_api" name="deepseek_settings[use_local_api]" 
                  <?php checked($use_local_api, true); ?> value="1" />
            <?php _e('Включить использование локального API-сервиса', 'deepseek-product-descriptions'); ?>
        </label>
        <p class="description">
            <?php _e('Выберите эту опцию, если вы хотите использовать локальный Flask-сервис вместо прямого подключения к OpenRouter. Это полезно для тестирования или если у вас есть собственный API-сервис.', 'deepseek-product-descriptions'); ?>
        </p>
        <?php
    }
    
    /**
     * Отображение поля для URL локального API
     */
    public function render_local_api_url_field($args) {
        $settings = get_option('deepseek_settings', array());
        $local_api_url = isset($settings['local_api_url']) ? $settings['local_api_url'] : 'http://localhost:5000/api/generate';
        
        ?>
        <input type="url" id="local_api_url" name="deepseek_settings[local_api_url]" 
               value="<?php echo esc_url($local_api_url); ?>" class="regular-text" />
        <p class="description">
            <?php _e('URL локального API-сервиса. По умолчанию это http://localhost:5000/api/generate', 'deepseek-product-descriptions'); ?>
        </p>
        <?php
    }
    
    /**
     * Отображение поля для опции автосохранения
     */
    public function render_auto_save_field($args) {
        $settings = get_option('deepseek_settings', array());
        $auto_save = isset($settings['auto_save_descriptions']) ? (bool) $settings['auto_save_descriptions'] : false;
        
        ?>
        <label for="auto_save_descriptions">
            <input type="checkbox" id="auto_save_descriptions" name="deepseek_settings[auto_save_descriptions]" 
                  <?php checked($auto_save, true); ?> value="1" />
            <?php _e('Автоматически сохранять сгенерированные описания', 'deepseek-product-descriptions'); ?>
        </label>
        <p class="description">
            <?php _e('Если включено, сгенерированные описания будут автоматически сохраняться без необходимости нажимать кнопку "Сохранить".', 'deepseek-product-descriptions'); ?>
        </p>
        <?php
    }
    
    /**
     * Отображение поля для опции обновления основного описания
     */
    public function render_update_main_description_field($args) {
        $settings = get_option('deepseek_settings', array());
        $update_main = isset($settings['update_main_description']) ? (bool) $settings['update_main_description'] : false;
        
        ?>
        <label for="update_main_description">
            <input type="checkbox" id="update_main_description" name="deepseek_settings[update_main_description]" 
                  <?php checked($update_main, true); ?> value="1" />
            <?php _e('Также обновлять основное описание товара', 'deepseek-product-descriptions'); ?>
        </label>
        <p class="description">
            <?php _e('Если включено, сохранение полного описания также обновит основное описание товара в WooCommerce.', 'deepseek-product-descriptions'); ?>
        </p>
        <?php
    }
    
    /**
     * Отображение поля для опции обновления выдержки
     */
    public function render_update_excerpt_field($args) {
        $settings = get_option('deepseek_settings', array());
        $update_excerpt = isset($settings['update_excerpt']) ? (bool) $settings['update_excerpt'] : false;
        
        ?>
        <label for="update_excerpt">
            <input type="checkbox" id="update_excerpt" name="deepseek_settings[update_excerpt]" 
                  <?php checked($update_excerpt, true); ?> value="1" />
            <?php _e('Также обновлять выдержку товара', 'deepseek-product-descriptions'); ?>
        </label>
        <p class="description">
            <?php _e('Если включено, сохранение короткого описания также обновит выдержку товара в WooCommerce.', 'deepseek-product-descriptions'); ?>
        </p>
        <?php
    }
    
    /**
     * Санитизация полей настроек
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API Key
        $sanitized['openrouter_api_key'] = sanitize_text_field($input['openrouter_api_key']);
        
        // Use Local API
        $sanitized['use_local_api'] = isset($input['use_local_api']) ? (bool) $input['use_local_api'] : false;
        
        // Local API URL
        $sanitized['local_api_url'] = esc_url_raw($input['local_api_url']);
        
        // Auto-save descriptions
        $sanitized['auto_save_descriptions'] = isset($input['auto_save_descriptions']) ? (bool) $input['auto_save_descriptions'] : false;
        
        // Update main description
        $sanitized['update_main_description'] = isset($input['update_main_description']) ? (bool) $input['update_main_description'] : false;
        
        // Update excerpt
        $sanitized['update_excerpt'] = isset($input['update_excerpt']) ? (bool) $input['update_excerpt'] : false;
        
        return $sanitized;
    }
}