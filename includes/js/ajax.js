jQuery(document).ready(function ($) {
    let vars = jQuery("#wt_backups_vars");
    let page = vars.attr('data-page');
    let notifications = JSON.parse(vars.attr('data-notifications'));
    let wt_backups_max_file_upload_in_bytes = vars.attr('data-maxfile');

    const url = new URL(document.location);
    const searchParams = url.searchParams;
    if(searchParams.has("storage")){
        searchParams.delete("storage");
        searchParams.delete("status");
        searchParams.delete("access_token");
        searchParams.delete("refresh_token");
        searchParams.delete("expires_in");
        searchParams.delete("code");
        searchParams.delete("state");
        window.history.pushState({}, '', url.toString());
    }

    $.each(notifications, function( index, value ) {
        toastr[value.type_raw](value.text);
    });

    var progress_checker = (process, page_nonce, logger) => {

        jQuery.ajax({
            url: ajaxurl,
            data: {
                action: 'wt_backups_progress_checker',
                ajax_action: 'progress_checker',
                process: process,
                ajax_nonce: page_nonce,
            },
            type: 'POST',
            success: function (data) {
                if (data.logger) {
                    logger.html(data.logger);
                    if (logger.attr('data-autoscroll') === "1") {
                        logger.scrollTop(logger.prop('scrollHeight'));
                    }
                }

                jQuery('.progress').css('translate', (data.progress - 100) + '%');

                if (data.progress === 100 || data.progress === '100') {
                    backup_build_next_page(process, data.page_nonce);
                } else {
                    progress_checker(process, data.page_nonce, logger);
                }

            },
            error: function (error) {
                if (error.progress === 100 || error.progress === '100') {
                    backup_build_next_page(process, error.page_nonce);
                } else {
                    progress_checker(process, error.page_nonce, logger);
                }
            }
        })

    };

    function backup_build_next_page(process, page_nonce) {

        jQuery.post(ajaxurl, {
            action: 'wt_backups_next_page',
            backup_action: 'backup_build_next_page',
            process: process,
            file: jQuery("#logger").attr('data-file'),
            ajax_nonce: page_nonce,
        }, function (response) {
            jQuery('#wt_backups_notifications').html(response.notifications);
            $.each(response.notifications_row, function( index, value ) {
                toastr[value.type_raw](value.text);
            });
            if (response.content) {
                jQuery('#wt-backups-wrap').html(response.content);
                var logger = jQuery("#logger");
                logger.on("scroll", function () {
                    logger.attr('data-autoscroll', 0)
                    setTimeout(function () {
                        logger.attr('data-autoscroll', 1);
                    }, 60000);
                });

                ajax_process(logger, response.page_nonce);
                setTimeout(() => {
                    progress_checker(logger.attr('data-process'), logger.attr('data-nonce'), logger)
                }, 2000);
            }

        });
    }
    function ajax_process(logger, page_nonce) {

        jQuery.post(ajaxurl, {
            action: 'wt_backups_' + logger.attr('data-process'),
            file: logger.attr('data-file'),
            ajax_nonce: page_nonce,
        }, function (response) {
            jQuery('#wt_backups_notifications').html(response.notifications);
            $.each(response.notifications_row, function( index, value ) {
                toastr[value.type_raw](value.text);
            });
        });
    }


    // activation.php
    if(page === 'activation') {
        jQuery('#wt_backups-activation-form').on('submit', function (e) {

            e.preventDefault();

            jQuery.post(ajaxurl, {

                action: 'wt_backups_activation',
                ajax_nonce: jQuery(this).attr('data-nonce'),
                api_key: jQuery('#api_key').val(),

            }, function (data) {
                jQuery('#wt_backups_notifications').html(data.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });

                if(data.success){
                    window.open(data.link, '_self');
                }

            });

        });
    }

    // settings.php
    if(page === 'settings') {
        jQuery('#backup_settings').on('submit', function (e) {

            e.preventDefault();

            let btn = jQuery('#save_settings');
            btn.addClass('wt_backups_loader_spinner');

            var data = $(this).serialize();
            data += '&action=wt_backups_save_backup_settings&ajax_action=&ajax_nonce=' + jQuery(this).attr('data-nonce');

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: data,
                success: function(response) {
                    btn.removeClass('wt_backups_loader_spinner');
                    jQuery('#wt_backups_notifications').html(response.notifications);
                    $.each(response.notifications_row, function( index, value ) {
                        toastr[value.type_raw](value.text);
                    });
                }
            });

        });

        jQuery('#choose_folders').on('click', function (e) {
            if ($(this).is(':checked')) {
                jQuery('#folders').show().find('input').removeAttr("disabled");
            } else {
                jQuery('#folders').hide().find('input').attr("disabled", true);
            }
        });

        jQuery('#db_only').on('change', function (e) {
            if ($(this).is(':checked')) {
                jQuery('.backup_folders').attr("disabled", true).removeAttr('checked');
                jQuery('#folders').hide();
            } else {
                jQuery('.backup_folders').removeAttr("disabled")
            }
        });

        jQuery('#check_settings').on('click', function (e) {
            e.preventDefault();
            let btn = jQuery(this);
            btn.addClass('wt_backups_loader_spinner');
            var data = $('#backup_settings').serialize();
            data += '&action=wt_backups_check_backup_settings&ajax_nonce=' + jQuery(this).attr('data-nonce');

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: data,
                success: function(response) {
                    btn.removeClass('wt_backups_loader_spinner');

                    jQuery('#wt_backups_notifications').html(response.notifications);
                    $.each(response.notifications_row, function( index, value ) {
                        toastr[value.type_raw](value.text);
                    });
                }
            });

        });

    }

    // add_storage.php
    if(page === 'addstorage'){
        jQuery('#save-storage').on('click', function (e) {
            e.preventDefault();
            let btn = jQuery(this);
            btn.prop("disabled",true);
            btn.addClass('wt_backups_loader_spinner');
            var data = $('#backup_storage_form').serialize();
            data += '&action=wt_backups_save_storage&ajax_nonce=' + jQuery(this).attr('data-nonce');

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: data,
                success: function(response) {
                    jQuery('#wt_backups_notifications').html(response.notifications);
                    $.each(response.notifications_row, function( index, value ) {
                        toastr[value.type_raw](value.text);
                    });
                    btn.prop("disabled",false).removeClass('wt_backups_loader_spinner');
                }
            });

        });

        jQuery('#add_google_drive').on('click', function (e) {

            e.preventDefault();

            jQuery.post(ajaxurl, {
                action: 'wt_backups_add_cloud_storage',
                storage: 'google_drive',
                ajax_nonce: jQuery(this).attr('data-nonce'),
                prev_url: document.referrer
            }, function (response) {
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
                if(response.redirect_link) { window.location.href = response.redirect_link; }
            });
        });

        jQuery('#add_dropbox_storage').on('click', function (e) {

            e.preventDefault();

            jQuery.post(ajaxurl, {
                action: 'wt_backups_add_cloud_storage',
                storage: 'dropbox',
                ajax_nonce: jQuery(this).attr('data-nonce'),
                prev_url: document.referrer
            }, function (response) {
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
                if(response.redirect_link) { window.location.href = response.redirect_link; }
            });
        });


        jQuery('#check_folder_path').on('click', function (e) {
            e.preventDefault();

            var folder_path = jQuery('#folder_path').val();

            jQuery.post(ajaxurl, {
                folder_path: folder_path,
                action: 'wt_backups_check_folder_path',
                ajax_nonce: jQuery(this).attr('data-nonce'),
            }, function (response) {
                jQuery('#check_path_result').html(response.result);
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
            });
        });

        jQuery('#check_ftp_connection').on('click', function (e) {

            e.preventDefault();

            jQuery.post(ajaxurl, {
                ftp_type : jQuery('#ftp_type').val(),
                ftp_host : jQuery('#ftp_host').val(),
                ftp_path : jQuery('#ftp_path').val(),
                ftp_port : jQuery('#ftp_port').val(),
                ftp_user : jQuery('#ftp_user').val(),
                ftp_password : jQuery('#ftp_password').val(),
                action: 'wt_backups_check_ftp',
                ajax_nonce: jQuery(this).attr('data-nonce'),
            }, function (response) {
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
            });
        });
    }

    // storages.php
    $('body').on('click', '.remove_storage', function (e) {
        e.preventDefault();
        let btn = jQuery(this);
        btn.addClass('wt_backups_loader_spinner');

        var key = jQuery(this).attr('data-key');

        jQuery.post(ajaxurl, {
            key: key,
            action: 'wt_backups_remove_storage',
            ajax_nonce: jQuery(this).attr('data-nonce'),
        }, function (response) {
            btn.removeClass('save_storage');
            jQuery('#storages_list_wrap').html(response.content);
            jQuery('#wt_backups_notifications').html(response.notifications);
            $.each(response.notifications_row, function( index, value ) {
                toastr[value.type_raw](value.text);
            });
        });

    });

    // prerestore.php
    jQuery("body").on('click', '#start_restore', function (e) {
        e.preventDefault();

        jQuery.post(ajaxurl, {
            action: 'wt_backups_restore_page',
            restore_action: 'restore_page',
            file: jQuery(this).attr('data-filename'),
            ajax_nonce: jQuery(this).attr('data-nonce'),
        }, function (response) {
            jQuery('#wt_backups_notifications').html(response.notifications);
            $.each(response.notifications_row, function( index, value ) {
                toastr[value.type_raw](value.text);
            });

            if(response.content){
                jQuery('#wt-backups-wrap').html(response.content);
                var logger = jQuery("#logger");
                logger.on("scroll", function () {
                    logger.attr('data-autoscroll', 0)
                    setTimeout(function () {
                        logger.attr('data-autoscroll', 1);
                    }, 60000);
                });

                ajax_process(logger, response.page_nonce);
                setTimeout(() => {
                    progress_checker(logger.attr('data-process'), logger.attr('data-nonce'), logger)
                }, 2000);
            }
        });

    }).on('click', '#start_building', function (e) {
        e.preventDefault();

        jQuery.post(ajaxurl, {
            action: 'wt_backups_next_page',
            backup_action: 'backup_start_building_page',
            ajax_nonce: jQuery(this).attr('data-nonce'),
        }, function (response) {

            jQuery('#wt_backups_notifications').html(response.notifications);
            $.each(response.notifications_row, function( index, value ) {
                toastr[value.type_raw](value.text);
            });

            if(response.content){
                jQuery('#wt-backups-wrap').html(response.content);
                var logger = jQuery("#logger");
                logger.on("scroll", function () {
                    logger.attr('data-autoscroll', 0)
                    setTimeout(function () {
                        logger.attr('data-autoscroll', 1);
                    }, 60000);
                });

                ajax_process(logger, response.page_nonce);
                setTimeout(() => {
                    progress_checker(logger.attr('data-process'), logger.attr('data-nonce'), logger)
                }, 2000);
            }

        });

        // popup.php
    }).on('click', '#wt-continue', function (e) {
        jQuery('.popup-content').addClass('wt_backups_loader_spinner');
        let btn = jQuery(this);

        if(jQuery(this).attr('data-action') == 'delete_backup'){
            jQuery.post(ajaxurl, {

                action: 'wt_backups_delete_backup',
                ajax_action: 'delete_backup',
                value: btn.attr('data-value'),
                ajax_nonce: btn.attr('data-nonce'),
            }, function (data) {
                jQuery('#wt_backups_notifications').html(data.notifications);


                // jQuery(this).parents('.popup-overlay').addClass('d-none');
                btn.closest('#confirm-popup').remove();
                jQuery('.popup-content').removeClass('wt_backups_loader_spinner');

                jQuery('#backups_list_wrap').html(data.content);
                jQuery('#backups_count').html(data.backups_count);

            });
        }

        if(jQuery(this).attr('data-action') == 'restore_page'){
            jQuery.post(ajaxurl, {
                action: 'wt_backups_restore_page',
                restore_action: 'restore_checking',
                file: btn.attr('data-value'),
                ajax_nonce: btn.attr('data-nonce'),
            }, function (response) {
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });

                jQuery('.popup-overlay').addClass('d-none');
                jQuery('#confirm-popup').remove();
                jQuery('.popup-content').removeClass('wt_backups_loader_spinner');

                if(response.content){
                    jQuery('#wt-backups-wrap main').html(response.content);
                    var logger = jQuery("#logger");
                    logger.on("scroll", function () {
                        logger.attr('data-autoscroll', 0)
                        setTimeout(function () {
                            logger.attr('data-autoscroll', 1);
                        }, 60000);
                    });

                    ajax_process(logger, response.page_nonce);
                    setTimeout(() => {
                        progress_checker(logger.attr('data-process'), logger.attr('data-nonce'), logger)
                    }, 2000);
                }
            });
        }
    });

    jQuery('body').on('click', '#wt-cancel', function (e) {
        jQuery('#confirm-popup').remove();
        $('body').removeClass('lock');
        $('#wpcontent').removeClass('wt-backups-wrap');
    });

    $('#wpcontent').on('click', '.popup-overlay', function (e) {
        if (e.target.className.includes('popup-overlay')) {
            $('.popup-overlay').addClass('d-none');
            $('body').removeClass('lock');
            $('#wpcontent').removeClass('wt-backups-wrap');
        }

    });

    // create_backup.php
    if(page === 'createbackup'){
        jQuery('#check_folder_path').on('click', function (e) {

            e.preventDefault();

            var folder_path = jQuery('#folder_path').val();

            jQuery.post(ajaxurl, {
                folder_path: folder_path,
                action: 'wt_backups_check_folder_path',
                ajax_nonce: jQuery(this).attr('data-nonce'),
            }, function (response) {
                jQuery('#check_path_result').html(response.result);
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
            });
        });

        jQuery('#backup_name').bind('input', function (e) {

            jQuery.post(ajaxurl, {
                backup_name: jQuery(this).val(),
                action: 'wt_backups_check_zip_exist',
                ajax_nonce: jQuery(this).attr('data-nonce'),
            }, function (response) {
                jQuery('#check_zip_exist_result').html('<span class="wt_backups_text_status_' + response.status.type + '">' + response.status.message + '</span>');
                jQuery('#wt_backups_notifications').html(response.notifications);
                $.each(response.notifications_row, function( index, value ) {
                    toastr[value.type_raw](value.text);
                });
            });
        });

        jQuery('#backup_settings').on('submit', function (e) {
            e.preventDefault();

            var data = $(this).serialize();
            data += '&action=wt_backups_next_page&backup_action=backup_checking_page&ajax_nonce=' + jQuery(this).attr('data-nonce');

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: data,
                success: function(response) {
                    jQuery('#wt_backups_notifications').html(response.notifications);
                    $.each(response.notifications_row, function( index, value ) {
                        toastr[value.type_raw](value.text);
                    });

                    if(response.content){
                        jQuery('#wt-backups-wrap').html(response.content);
                        var logger = jQuery("#logger");
                        logger.on("scroll", function () {
                            logger.attr('data-autoscroll', 0)
                            setTimeout(function () {
                                logger.attr('data-autoscroll', 1);
                            }, 60000);
                        });

                        ajax_process(logger, response.page_nonce);
                        setTimeout(() => {
                            progress_checker(logger.attr('data-process'), logger.attr('data-nonce'), logger)
                        }, 2000);
                    }
                }
            });

        });

        jQuery('#choose_folders').on('click', function (e) {
            if ($(this).is(':checked')) {
                jQuery('#folders').show().find('input').removeAttr("disabled");
            } else {
                jQuery('#folders').hide().find('input').attr("disabled", true);
            }
        });

        jQuery('#db_only').on('change', function (e) {
            if ($(this).is(':checked')) {
                jQuery('.backup_folders').attr("disabled", true).removeAttr('checked');
                jQuery('#folders').hide();
            } else {
                jQuery('.backup_folders').removeAttr("disabled")
            }
        });
    }

    // dashboard.php
    jQuery('body').on('click', '.open-popup', function (e) {
        jQuery.post(
            ajaxurl,
            {
                action: 'wt_backups_open_popup',
                popup_action: jQuery(this).data('action'),
                file: jQuery(this).attr('data-file'),
                ajax_nonce: jQuery(this).attr('data-nonce'),
            },
            function (data) {
                if(data.success){
                    jQuery('#wpcontent').append(data.content).addClass('wt-backups-wrap');
                }
            }
        );
    });


    $("#js-file").change(function(e){
        var file = $("#js-file")[0].files[0];
        if(file.size > wt_backups_max_file_upload_in_bytes){
            jQuery('#upload_file_message').html('<p style="color: red">The file is too large</p>');
        }
        else if(window.FormData === undefined) {
            alert('Form Data is not supported in your browser')
        } else {
            e.preventDefault();

            var data = new FormData();
            data.append('backup_file', file);
            data.append('action', 'wt_backups_upload_backup');
            data.append('ajax_nonce', jQuery(this).attr('data-nonce'));

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: data,
                contentType: false,
                processData: false,
                success: function(response) {
                    jQuery('#wt_backups_notifications').html(response.notifications);
                    $.each(response.notifications_row, function( index, value ) {
                        toastr[value.type_raw](value.text);
                    });
                    jQuery('#backups_list_wrap').html(response.content);
                    jQuery('#backups_count').html(response.backups_count);
                }
            });

        }
    });



});