/**
 * JavaScript для плагина "Генератор описаний товаров"
 * Обрабатывает взаимодействие с пользовательским интерфейсом и AJAX-запросы для плагина
 */

// Функция для проверки, является ли строка ID пользователя OpenRouter
function isOpenRouterUserId(text) {
    if (!text || typeof text !== 'string') return false;
    return text.match(/^user_[a-zA-Z0-9]{20,}$/) !== null || 
           (text.includes('user_') && text.length < 40);
}

jQuery(document).ready(function($) {
    /**
     * Обработчики для полного описания
     */
    // Клик по кнопке редактирования полного описания
    $(document).on('click', '.edit-full-desc', function() {
        $(this).closest('.deepseek-description-container').find('.full-desc-editor').toggle();
    });
    
    // Клик по кнопке генерации полного описания
    $(document).on('click', '.generate-full-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const currentDescription = container.find('.old-full-desc').val();
        const responseMsg = container.find('.response-message');
        const generateBtn = $(this);
        const originalText = generateBtn.text();
        
        // Обновление интерфейса
        generateBtn.text(deepseek_data.generating_text);
        generateBtn.prop('disabled', true);
        responseMsg.html('').removeClass('error success');
        
        // AJAX-запрос для генерации описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_full_description',
                post_id: postId,
                current_description: currentDescription,
                nonce: deepseek_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Проверка на возврат user ID вместо описания
                    const description = response.data.description;
                    if (description && typeof description === 'string' && description.match(/^user_[a-zA-Z0-9]{20,}$/)) {
                        responseMsg.html('Ошибка формата ответа. Повторная попытка...').addClass('error');
                        
                        // Автоматически повторяем запрос через 2 секунды
                        setTimeout(function() {
                            generateBtn.click();
                        }, 2000);
                        return;
                    }
                    
                    container.find('.new-full-desc').val(description);
                    
                    if (response.data.auto_saved) {
                        responseMsg.html('✓ Описание сгенерировано и сохранено.').addClass('success');
                        
                        // Обновляем индикатор после сохранения
                        container.find('.status-dot')
                            .removeClass('no-desc')
                            .addClass('has-desc')
                            .attr('title', 'Есть описание')
                            .text('•');
                    } else {
                        responseMsg.html('✓ Описание сгенерировано. Нажмите "Сохранить" для сохранения.').addClass('success');
                    }
                } else {
                    responseMsg.html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                responseMsg.html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                generateBtn.text(originalText);
                generateBtn.prop('disabled', false);
            }
        });
    });
    
    // Быстрая генерация и автосохранение полного описания
    $(document).on('click', '.generate-direct-full-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const currentDescription = container.find('.old-full-desc').val();
        const statusButton = $(this);
        const originalText = statusButton.text();
        
        // Визуальная индикация процесса
        statusButton.text('•••');
        statusButton.prop('disabled', true);
        
        // AJAX-запрос для генерации и сохранения описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_full_description',
                post_id: postId,
                current_description: currentDescription,
                nonce: deepseek_data.nonce,
                auto_save: true // Принудительное автосохранение
            },
            success: function(response) {
                if (response.success) {
                    // Проверка на возврат user ID вместо описания
                    const description = response.data.description;
                    if (description && typeof description === 'string' && description.match(/^user_[a-zA-Z0-9]{20,}$/)) {
                        // Визуальная индикация ошибки
                        statusButton.text('⟳');
                        
                        // Автоматически повторяем запрос через 2 секунды
                        setTimeout(function() {
                            statusButton.text(originalText);
                            statusButton.prop('disabled', false);
                            statusButton.click();
                        }, 2000);
                        return;
                    }
                    
                    // Визуальное подтверждение
                    statusButton.text('✓');
                    
                    // Обновляем индикатор
                    container.find('.status-dot')
                        .removeClass('no-desc')
                        .addClass('has-desc')
                        .attr('title', 'Есть описание')
                        .text('•');
                        
                    // Делаем доступной старую textarea если она была скрыта
                    container.find('.old-full-desc').val(description);
                    
                    // Через 1.5 секунды возвращаем исходный текст кнопки
                    setTimeout(function() {
                        statusButton.text(originalText);
                    }, 1500);
                } else {
                    // Визуальная индикация ошибки
                    statusButton.text('✗');
                    setTimeout(function() {
                        statusButton.text(originalText);
                    }, 1500);
                    
                    // Показываем редактор с сообщением об ошибке
                    container.find('.full-desc-editor').show();
                    container.find('.response-message').html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                // Визуальная индикация ошибки
                statusButton.text('✗');
                setTimeout(function() {
                    statusButton.text(originalText);
                }, 1500);
                
                // Показываем редактор с сообщением об ошибке
                container.find('.full-desc-editor').show();
                container.find('.response-message').html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                statusButton.prop('disabled', false);
            }
        });
    });
    
    // Клик по кнопке сохранения полного описания
    $(document).on('click', '.save-full-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const newDescription = container.find('.new-full-desc').val();
        const responseMsg = container.find('.response-message');
        const saveBtn = $(this);
        const originalText = saveBtn.text();
        
        // Проверка, не пустое ли описание
        if (!newDescription.trim()) {
            responseMsg.html('Ошибка: Описание не может быть пустым').addClass('error');
            return;
        }
        
        // Обновление интерфейса
        saveBtn.text(deepseek_data.saving_text);
        saveBtn.prop('disabled', true);
        responseMsg.html('').removeClass('error success');
        
        // AJAX-запрос для сохранения описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'save_full_description',
                post_id: postId,
                description: newDescription,
                nonce: deepseek_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Обновление поля старого описания новым содержимым
                    container.find('.old-full-desc').val(newDescription);
                    responseMsg.html('✓ ' + response.data.message).addClass('success');
                    
                    // Обновляем индикатор
                    container.find('.status-dot')
                        .removeClass('no-desc')
                        .addClass('has-desc')
                        .attr('title', 'Есть описание')
                        .text('•');
                        
                    // Скрытие редактора после короткой задержки
                    setTimeout(function() {
                        container.find('.full-desc-editor').hide();
                    }, 1500);
                } else {
                    responseMsg.html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                responseMsg.html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                saveBtn.text(originalText);
                saveBtn.prop('disabled', false);
            }
        });
    });
    
    // Клик по кнопке отмены редактирования
    $(document).on('click', '.cancel-edit', function() {
        $(this).closest('.full-desc-editor, .short-desc-editor').hide();
    });
    
    /**
     * Обработчики для короткого описания
     */
    // Клик по кнопке редактирования короткого описания
    $(document).on('click', '.edit-short-desc', function() {
        $(this).closest('.deepseek-description-container').find('.short-desc-editor').toggle();
    });
    
    // Клик по кнопке генерации короткого описания
    $(document).on('click', '.generate-short-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const currentDescription = container.find('.old-short-desc').val();
        const responseMsg = container.find('.response-message');
        const generateBtn = $(this);
        const originalText = generateBtn.text();
        
        // Обновление интерфейса
        generateBtn.text(deepseek_data.generating_text);
        generateBtn.prop('disabled', true);
        responseMsg.html('').removeClass('error success');
        
        // AJAX-запрос для генерации короткого описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_short_description',
                post_id: postId,
                current_description: currentDescription,
                nonce: deepseek_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Проверка на возврат user ID вместо описания
                    const description = response.data.description;
                    if (description && typeof description === 'string' && description.match(/^user_[a-zA-Z0-9]{20,}$/)) {
                        responseMsg.html('Ошибка формата ответа. Повторная попытка...').addClass('error');
                        
                        // Автоматически повторяем запрос через 2 секунды
                        setTimeout(function() {
                            generateBtn.click();
                        }, 2000);
                        return;
                    }
                    
                    container.find('.new-short-desc').val(description);
                    
                    if (response.data.auto_saved) {
                        responseMsg.html('✓ Короткое описание сгенерировано и сохранено.').addClass('success');
                        
                        // Обновляем индикатор после сохранения
                        container.find('.status-dot')
                            .removeClass('no-desc')
                            .addClass('has-desc')
                            .attr('title', 'Есть описание')
                            .text('•');
                    } else {
                        responseMsg.html('✓ Описание сгенерировано. Нажмите "Сохранить" для сохранения.').addClass('success');
                    }
                } else {
                    responseMsg.html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                responseMsg.html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                generateBtn.text(originalText);
                generateBtn.prop('disabled', false);
            }
        });
    });
    
    // Быстрая генерация и автосохранение короткого описания
    $(document).on('click', '.generate-direct-short-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const currentDescription = container.find('.old-short-desc').val();
        const statusButton = $(this);
        const originalText = statusButton.text();
        
        // Визуальная индикация процесса
        statusButton.text('•••');
        statusButton.prop('disabled', true);
        
        // AJAX-запрос для генерации и сохранения описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_short_description',
                post_id: postId,
                current_description: currentDescription,
                nonce: deepseek_data.nonce,
                auto_save: true // Принудительное автосохранение
            },
            success: function(response) {
                if (response.success) {
                    // Проверка на возврат user ID вместо описания
                    const description = response.data.description;
                    if (description && typeof description === 'string' && description.match(/^user_[a-zA-Z0-9]{20,}$/)) {
                        // Визуальная индикация ошибки
                        statusButton.text('⟳');
                        
                        // Автоматически повторяем запрос через 2 секунды
                        setTimeout(function() {
                            statusButton.text(originalText);
                            statusButton.prop('disabled', false);
                            statusButton.click();
                        }, 2000);
                        return;
                    }
                    
                    // Визуальное подтверждение
                    statusButton.text('✓');
                    
                    // Обновляем индикатор
                    container.find('.status-dot')
                        .removeClass('no-desc')
                        .addClass('has-desc')
                        .attr('title', 'Есть описание')
                        .text('•');
                        
                    // Делаем доступной старую textarea если она была скрыта
                    container.find('.old-short-desc').val(description);
                    
                    // Через 1.5 секунды возвращаем исходный текст кнопки
                    setTimeout(function() {
                        statusButton.text(originalText);
                    }, 1500);
                } else {
                    // Визуальная индикация ошибки
                    statusButton.text('✗');
                    setTimeout(function() {
                        statusButton.text(originalText);
                    }, 1500);
                    
                    // Показываем редактор с сообщением об ошибке
                    container.find('.short-desc-editor').show();
                    container.find('.response-message').html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                // Визуальная индикация ошибки
                statusButton.text('✗');
                setTimeout(function() {
                    statusButton.text(originalText);
                }, 1500);
                
                // Показываем редактор с сообщением об ошибке
                container.find('.short-desc-editor').show();
                container.find('.response-message').html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                statusButton.prop('disabled', false);
            }
        });
    });
    
    // Клик по кнопке сохранения короткого описания
    $(document).on('click', '.save-short-desc', function() {
        const container = $(this).closest('.deepseek-description-container');
        const postId = container.data('id');
        const newDescription = container.find('.new-short-desc').val();
        const responseMsg = container.find('.response-message');
        const saveBtn = $(this);
        const originalText = saveBtn.text();
        
        // Проверка, не пустое ли описание
        if (!newDescription.trim()) {
            responseMsg.html('Ошибка: Описание не может быть пустым').addClass('error');
            return;
        }
        
        // Обновление интерфейса
        saveBtn.text(deepseek_data.saving_text);
        saveBtn.prop('disabled', true);
        responseMsg.html('').removeClass('error success');
        
        // AJAX-запрос для сохранения описания
        $.ajax({
            url: deepseek_data.ajax_url,
            type: 'POST',
            data: {
                action: 'save_short_description',
                post_id: postId,
                description: newDescription,
                nonce: deepseek_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Обновление поля старого описания новым содержимым
                    container.find('.old-short-desc').val(newDescription);
                    responseMsg.html('✓ ' + response.data.message).addClass('success');
                    
                    // Обновляем индикатор
                    container.find('.status-dot')
                        .removeClass('no-desc')
                        .addClass('has-desc')
                        .attr('title', 'Есть описание')
                        .text('•');
                        
                    // Скрытие редактора после короткой задержки
                    setTimeout(function() {
                        container.find('.short-desc-editor').hide();
                    }, 1500);
                } else {
                    responseMsg.html('Ошибка: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                responseMsg.html('Произошла ошибка сервера. Пожалуйста, попробуйте снова.').addClass('error');
            },
            complete: function() {
                saveBtn.text(originalText);
                saveBtn.prop('disabled', false);
            }
        });
    });
});