/**
 * Модуль пакетной обработки и генерации описаний товаров
 * Расширяет функционал deepseek.js для работы с массовыми операциями
 */

// Функция для проверки, является ли строка ID пользователя OpenRouter
function isOpenRouterUserId(text) {
    if (!text || typeof text !== 'string') return false;
    return text.match(/^user_[a-zA-Z0-9]{20,}$/) !== null || 
           (text.includes('user_') && text.length < 40);
}

jQuery(document).ready(function($) {
    // Функция для пакетной генерации обоих описаний
    window.batchGenerateBothDescriptions = function(productIds) {
        console.log('Запуск batchGenerateBothDescriptions для товаров:', productIds);
        
        if (!productIds || productIds.length === 0) {
            console.error('Нет товаров для генерации!');
            alert(deepseek_data.select_products_message);
            return;
        }
        
        let processed = 0;
        let total = productIds.length * 2; // Два описания для каждого товара
        let statusContainer = $('<div class="deepseek-batch-status"></div>');
        let progressBar = $('<div class="deepseek-progress-bar"><div class="deepseek-progress-bar-inner"></div></div>');
        
        // Удаляем предыдущие статус-бары, если они есть
        $('.deepseek-batch-status').remove();
        
        // Добавляем статус-бар в верхнюю часть страницы
        $('.tablenav.top').after(statusContainer);
        statusContainer.append(progressBar);
        
        // Обновляем статус и прогресс-бар
        function updateStatus() {
            const percent = Math.floor((processed / total) * 100);
            progressBar.find('.deepseek-progress-bar-inner').css('width', percent + '%');
            
            const statusText = deepseek_data.processing_message.replace('{processed}', processed)
                .replace('{total}', total)
                .replace('{percent}', percent);
            
            if (!statusContainer.find('.status-text').length) {
                statusContainer.append('<div class="status-text"></div>');
            }
            statusContainer.find('.status-text').text(statusText);
        }
        
        // Инициализируем статус
        updateStatus();
        
        // Выделяем визуально выбранные товары
        productIds.forEach(function(pid) {
            $(`.wp-list-table tbody tr input[value="${pid}"]`).closest('tr').addClass('selected-for-generation');
        });
        
        // Обрабатываем каждый товар последовательно
        console.log('Начинаем обработку товаров...');
        processNext(0);
        
        function processNext(index) {
            console.log('processNext с индексом', index, 'из', productIds.length);
            
            if (index >= productIds.length) {
                console.log('Обработка завершена');
                // Завершаем процесс
                $('.deepseek-batch-status .status-text').text(deepseek_data.completed_message);
                $('.wp-list-table tbody tr').removeClass('selected-for-generation');
                
                // Плавное исчезновение статус-бара через 5 секунд
                setTimeout(function() {
                    $('.deepseek-batch-status').fadeOut(1000, function() {
                        $(this).remove();
                    });
                }, 5000);
                return;
            }
            
            // Получаем ID текущего товара
            const productId = productIds[index];
            console.log('Обработка товара ID:', productId);
            
            // Сначала генерируем полное описание, затем короткое
            generateFullDescription(productId, function() {
                generateShortDescription(productId, function() {
                    // Переходим к следующему товару
                    processNext(index + 1);
                });
            });
        }
        
        // Функция для генерации полного описания
        function generateFullDescription(productId, callback) {
            console.log('Генерация полного описания для товара ID:', productId);
            
            // Находим строку товара
            const row = $(`.wp-list-table tbody tr input[value="${productId}"]`).closest('tr');
            row.css('background-color', '#f0fff0'); // Зеленый фон строки для индикации обработки
            
            // Дополнительная отладка для поиска контейнера
            let containerCount = $(`.deepseek-description-container[data-id="${productId}"]`).length;
            console.log(`Найдено контейнеров с data-id=${productId}: ${containerCount}`);
            
            // Если контейнер не найден по data-id, попробуем найти через родительскую строку
            let container;
            if (containerCount === 0) {
                console.log('Пытаемся найти контейнер через row...');
                container = row.find('.deepseek-description-container').first();
                if (container.length === 0) {
                    console.error(`Не удалось найти контейнер для товара ID: ${productId}`);
                    processed++; // Увеличиваем счетчик несмотря на ошибку
                    updateStatus();
                    if (typeof callback === 'function') callback();
                    return;
                }
            } else {
                container = $(`.deepseek-description-container[data-id="${productId}"]`).first();
            }
            
            // Проверяем наличие текстовой области
            let textareaExists = container.find('.old-full-desc').length > 0;
            console.log(`Текстовая область найдена: ${textareaExists}`);
            
            const currentDescription = textareaExists ? 
                (container.find('.old-full-desc').val() || '') : 
                '';
            
            $.ajax({
                url: deepseek_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_full_description',
                    post_id: productId,
                    current_description: currentDescription,
                    nonce: deepseek_data.nonce,
                    auto_save: true // Принудительное автосохранение
                },
                success: function(response) {
                    if (response.success) {
                        // Проверяем, не вернулся ли ID пользователя вместо описания
                        const description = response.data.description || '';
                        
                        if (isOpenRouterUserId(description)) {
                            console.error('Получен ID пользователя вместо описания:', description);
                            // Устанавливаем индикатор повторной попытки
                            container.find('.status-dot')
                                .removeClass('no-desc')
                                .removeClass('has-desc')
                                .addClass('retry')
                                .attr('title', deepseek_data.retry_message)
                                .text('⟳');
                            
                            // Повторяем запрос через небольшую задержку
                            setTimeout(function() {
                                generateFullDescription(productId, callback);
                            }, 1500);
                            return;
                        }
                        
                        // Обновляем индикатор
                        container.find('.status-dot')
                            .removeClass('no-desc')
                            .removeClass('retry')
                            .addClass('has-desc')
                            .attr('title', deepseek_data.has_desc_message)
                            .text('•');
                        
                        processed++;
                        updateStatus();
                        
                        // Переходим к следующему шагу
                        if (typeof callback === 'function') callback();
                    } else {
                        console.error('Ошибка генерации полного описания для товара ID:', productId, response.data.message);
                        row.css('background-color', '#fff0f0'); // Красный фон при ошибке
                        processed++;
                        updateStatus();
                        if (typeof callback === 'function') callback();
                    }
                },
                error: function() {
                    console.error('Ошибка AJAX запроса для товара ID:', productId);
                    row.css('background-color', '#fff0f0'); // Красный фон при ошибке
                    processed++;
                    updateStatus();
                    if (typeof callback === 'function') callback();
                }
            });
        }
        
        // Функция для генерации короткого описания
        function generateShortDescription(productId, callback) {
            console.log('Генерация короткого описания для товара ID:', productId);
            
            // Находим строку товара
            const row = $(`.wp-list-table tbody tr input[value="${productId}"]`).closest('tr');
            
            // Дополнительная отладка для поиска контейнера
            let containerCount = $(`.deepseek-description-container[data-id="${productId}"]`).length;
            console.log(`Найдено контейнеров с data-id=${productId}: ${containerCount}`);
            
            // Если контейнер не найден по data-id, попробуем найти через родительскую строку
            let container;
            if (containerCount < 2) {
                console.log('Пытаемся найти контейнер для короткого описания через row...');
                // Находим все контейнеры в строке
                const rowContainers = row.find('.deepseek-description-container');
                console.log(`В строке найдено контейнеров: ${rowContainers.length}`);
                
                // Берем второй контейнер, если он есть, иначе первый
                container = rowContainers.length > 1 ? rowContainers.eq(1) : rowContainers.first();
                
                if (container.length === 0) {
                    console.error(`Не удалось найти контейнер для короткого описания товара ID: ${productId}`);
                    processed++;
                    updateStatus();
                    if (typeof callback === 'function') callback();
                    return;
                }
            } else {
                // Если есть несколько контейнеров, берем второй (для короткого описания)
                container = $(`.deepseek-description-container[data-id="${productId}"]`).eq(1);
            }
            
            // Проверяем наличие текстовой области
            let textareaExists = container.find('.old-short-desc').length > 0;
            console.log(`Текстовая область для короткого описания найдена: ${textareaExists}`);
            
            const currentDescription = textareaExists ? 
                (container.find('.old-short-desc').val() || '') : 
                '';
            
            $.ajax({
                url: deepseek_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_short_description',
                    post_id: productId,
                    current_description: currentDescription,
                    nonce: deepseek_data.nonce,
                    auto_save: true // Принудительное автосохранение
                },
                success: function(response) {
                    if (response.success) {
                        // Проверяем, не вернулся ли ID пользователя вместо описания
                        const description = response.data.description || '';
                        
                        if (isOpenRouterUserId(description)) {
                            console.error('Получен ID пользователя вместо короткого описания:', description);
                            // Устанавливаем индикатор повторной попытки
                            container.find('.status-dot')
                                .removeClass('no-desc')
                                .removeClass('has-desc')
                                .addClass('retry')
                                .attr('title', deepseek_data.retry_message)
                                .text('⟳');
                            
                            // Повторяем запрос через небольшую задержку
                            setTimeout(function() {
                                generateShortDescription(productId, callback);
                            }, 1500);
                            return;
                        }
                        
                        // Обновляем индикатор
                        container.find('.status-dot')
                            .removeClass('no-desc')
                            .removeClass('retry')
                            .addClass('has-desc')
                            .attr('title', deepseek_data.has_desc_message)
                            .text('•');
                            
                        processed++;
                        updateStatus();
                            
                        // Переходим к следующему шагу
                        if (typeof callback === 'function') callback();
                    } else {
                        console.error('Ошибка генерации короткого описания для товара ID:', productId, response.data.message);
                        processed++;
                        updateStatus();
                        if (typeof callback === 'function') callback();
                    }
                },
                error: function() {
                    console.error('Ошибка AJAX запроса для товара ID:', productId);
                    processed++;
                    updateStatus();
                    if (typeof callback === 'function') callback();
                }
            });
        }
    };
});