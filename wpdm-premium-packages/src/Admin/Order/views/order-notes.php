<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
use WPDMPP\UI\Icons;

// Allowed attachment types. "*" (alone or in the list) means all files allowed,
// so the file picker carries no accept filter and the client-side check is skipped.
$wpdm_note_allowed_types = (string) get_option('__wpdm_allowed_file_types', 'png,pdf,jpg,txt');
$wpdm_note_allow_all = trim($wpdm_note_allowed_types) === '*'
    || in_array('*', array_map('trim', explode(',', $wpdm_note_allowed_types)), true);
?>
<div id="all-notes">
<?php
$order_notes = maybe_unserialize($order->order_notes);

if(isset($order_notes['messages'])){
    foreach ($order_notes['messages'] as $time => $order_note) {
        $copy = array();
        if(isset($order_note['admin'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Admin &nbsp; ';
        if(isset($order_note['seller'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Seller &nbsp; ';
        if(isset($order_note['customer'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Customer &nbsp; ';
        $copy = implode("", $copy);
        ?>

        <div class="panel panel-default dashboard-panel">
            <div class="panel-body">
                <?php

                $note = wpautop(strip_tags(stripcslashes($order_note['note']),"<a><strong><b><img><br><em><i>")); echo preg_replace('/[\s]+((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a target="_blank" href="\1">\1</a>', $note); ?>

            </div>
            <?php if(isset($order_note['file']) && is_array($order_note['file'])){ ?>
                <div class="panel-footer text-right">
                    <?php foreach($order_note['file'] as $id => $file){ $aid = \WPDM\__\Crypt::Encrypt($order->order_id."|||".$time."|||".$file); ?>
                        <a href="<?php echo home_url("/?oid=".$order->order_id."&_atcdl=".$aid); ?>" style="margin-left: 10px"><?php echo Icons::get('paperclip', 14); ?> <?php echo $file; ?></a> &nbsp;
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="panel-footer text-right">
                <small><em><?php echo Icons::get('pencil', 12); ?> <?php echo $order_note['by']; ?> &nbsp; <?php echo Icons::get('clock', 12); ?> <?php echo wp_date(get_option('date_format') . " h:i", $time); ?></em></small>
                <div class="pull-left"><small><em><?php if($copy!='') echo "Copy sent to ".$copy; ?></em></small></div>
            </div>
        </div>
    <?php
    }
}
?>
</div>
<form method="post" id="post-order-note" data-order-id="<?php echo esc_attr($order->order_id); ?>">
    <div class="panel panel-default dashboard-panel">
        <textarea id="order-note" name="note" class="form-control" style="border: 0;box-shadow: none;min-height: 90px;max-width: 100%;min-width: 100%;padding: 10px"></textarea>

        <div id="wpdm-upload-ui" class="panel-footer image-selector-panel">
            <div id="filelist" class="pull-right"></div>
            <div id="wpdm-drag-drop-area">

                <label for="wpdm-browse-input" id="wpdm-browse-button" style="text-transform: unset;letter-spacing: 1px;cursor:pointer;margin-bottom:0;line-height: 24px" class="btn btn-xs btn-info"><?php _e("Select File", "download-manager");  ?></label>
                <input type="file" id="wpdm-browse-input" accept="<?php echo $wpdm_note_allow_all ? '' : esc_attr('.' . str_replace(',', ',.', $wpdm_note_allowed_types)); ?>" style="display:none" multiple />
                <div class="progress" id="wmprogressbar" style="width: 111px;height: 20px !important;border-radius: 2px !important;margin: 0;position: relative;background: #0d406799;display: none;box-shadow: none">
                    <div id="wmprogress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;line-height: 20px;background-color: #007bff"></div>
                    <div class="fetfont" style="font-size:8px;position: absolute;line-height: 20px;height: 20px;width: 100%;z-index: 999;text-align: center;color: #ffffff;letter-spacing: 1px">UPLOADING... <span id="wmloaded">0</span>%</div>
                </div>

                <script type="text/javascript">
                    jQuery(function($){
                        var uploadCfg = {
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            nonce: '<?php echo wp_create_nonce(NONCE_KEY); ?>',
                            allowed: <?php echo $wpdm_note_allow_all ? '[]' : wp_json_encode(array_values(array_map(static function ($e) { return strtolower(trim($e)); }, explode(',', $wpdm_note_allowed_types)))); ?>,
                            maxBytes: <?php echo (int) wp_max_upload_size(); ?>
                        };

                        var $browseBtn = $('#wpdm-browse-button');
                        var $browseInput = $('#wpdm-browse-input');
                        var $progressBar = $('#wmprogressbar');
                        var $progressFill = $('#wmprogress');
                        var $progressText = $('#wmloaded');
                        var $fileList = $('#filelist');
                        // Pre-rendered SVG icon kept in a JS var so its double-quoted
                        // attributes don't terminate the surrounding JS string literal
                        // (which would be a parse error that breaks this whole script).
                        var _wpdmppCloseIcon = <?php echo wp_json_encode( Icons::get('close', 12) ); ?>;

                        function formatSize(bytes) {
                            if (!bytes) return '0 B';
                            var units = ['B', 'KB', 'MB', 'GB'], i = 0;
                            while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
                            return bytes.toFixed(1) + ' ' + units[i];
                        }

                        function setProgress(pct) {
                            $progressFill.css('width', pct + '%');
                            $progressText.html(pct);
                        }

                        function uploadFile(file) {
                            var ext = (file.name.split('.').pop() || '').toLowerCase();
                            if (uploadCfg.allowed.length && uploadCfg.allowed.indexOf(ext) === -1) {
                                if (typeof WPDM !== 'undefined' && WPDM.bootAlert) {
                                    WPDM.bootAlert('Error', '<?php echo esc_js(__('File type not allowed.', 'wpdm-premium-packages')); ?> (' + ext + ')', 400);
                                } else {
                                    alert('<?php echo esc_js(__('File type not allowed.', 'wpdm-premium-packages')); ?>');
                                }
                                return;
                            }
                            if (uploadCfg.maxBytes && file.size > uploadCfg.maxBytes) {
                                if (typeof WPDM !== 'undefined' && WPDM.bootAlert) {
                                    WPDM.bootAlert('Error', '<?php echo esc_js(__('File is too large.', 'wpdm-premium-packages')); ?>', 400);
                                } else {
                                    alert('<?php echo esc_js(__('File is too large.', 'wpdm-premium-packages')); ?>');
                                }
                                return;
                            }

                            // Use the same uploader as the front-end (customer) order-note
                            // attachment: action wpdm_frontend_file_upload with the
                            // attach_file field, so both paths behave identically.
                            var form = new FormData();
                            form.append('action', 'wpdm_frontend_file_upload');
                            form.append('_ajax_nonce', uploadCfg.nonce);
                            form.append('section', 'wpdm_order_note');
                            form.append('name', file.name);
                            form.append('attach_file', file, file.name);

                            $browseBtn.hide();
                            $progressBar.show();
                            setProgress(0);

                            $.ajax({
                                url: uploadCfg.url,
                                type: 'POST',
                                data: form,
                                contentType: false,
                                processData: false,
                                xhr: function () {
                                    var xhr = new window.XMLHttpRequest();
                                    if (xhr.upload) {
                                        xhr.upload.addEventListener('progress', function (e) {
                                            if (e.lengthComputable) {
                                                setProgress(Math.round((e.loaded / e.total) * 100));
                                            }
                                        });
                                    }
                                    return xhr;
                                },
                                success: function (response) {
                                    $progressBar.hide();
                                    $browseBtn.show();
                                    setProgress(0);

                                    var parts = (typeof response === 'string' ? response : '').split('|||');
                                    var filename = parts.length > 1 ? parts[1] : '';
                                    if (!filename) {
                                        if (typeof WPDM !== 'undefined' && WPDM.bootAlert) {
                                            WPDM.bootAlert('Error', '<?php echo esc_js(__('Upload failed.', 'wpdm-premium-packages')); ?>', 400);
                                        }
                                        return;
                                    }
                                    var id = 'file_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
                                    var html = "<span id='" + id + "' class='atcf'><a href='#' rel='#" + id + "' class='del-file text-danger'>" + _wpdmppCloseIcon + "</a> &nbsp; <input type='hidden' name='file[]' value='" + filename.replace(/'/g, '&#39;') + "' />" + filename + "</span>";
                                    $fileList.prepend(html);
                                },
                                error: function () {
                                    $progressBar.hide();
                                    $browseBtn.show();
                                    if (typeof WPDM !== 'undefined' && WPDM.bootAlert) {
                                        WPDM.bootAlert('Error', '<?php echo esc_js(__('Upload failed.', 'wpdm-premium-packages')); ?>', 400);
                                    }
                                }
                            });
                        }

                        $browseInput.on('change', function () {

                            var files = this.files;
                            if (!files || !files.length) return;
                            for (var i = 0; i < files.length; i++) uploadFile(files[i]);
                            this.value = '';
                        });

                        var $dropZone = $('#wpdm-drag-drop-area');
                        $dropZone
                            .on('dragover dragenter', function (e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('drag-over'); })
                            .on('dragleave dragend drop', function (e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over'); })
                            .on('drop', function (e) {
                                var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
                                if (!files || !files.length) return;
                                for (var i = 0; i < files.length; i++) uploadFile(files[i]);
                            });

                        $fileList.on('click', '.del-file', function (e) {
                            e.preventDefault();
                            $($(this).attr('rel')).remove();
                        });
                    });
                </script>

                <div class="clear"></div>

            </div>
        </div>
        <div class="panel-footer text-right">
            <button type='button' id="sgc" class='btn btn-sm btn-secondary ttip' title="<?php _e('Spell and Grammar Check', WPDMPP_TEXT_DOMAIN); ?>"><?php echo Icons::get('check-double', 12); ?></button>
            <button type='button' id="air" class='btn btn-sm btn-secondary ttip' title="<?php _e('Reply with AI', WPDMPP_TEXT_DOMAIN); ?>"><?php echo Icons::get('settings', 12); ?></button>
            <button type='button' id="art" class='btn btn-sm btn-secondary ttip' title="<?php _e('Re-articulate', WPDMPP_TEXT_DOMAIN); ?>"><?php echo Icons::get('edit', 12); ?></button>

            <button data-toggle='modal' data-target='#ontmodal' type='button' class='btn btn-sm btn-info'><?php _e('Templates', WPDMPP_TEXT_DOMAIN); ?></button>
            <button class="btn btn-primary btn-sm" id="add-note-button" type="submit"><?php echo Icons::get('plus-circle', 12); ?> <?php _e('Add Note','wpdm-premium-packages'); ?></button>
            <div class="pull-left">
                <label><?php _e('Also mail to:','wpdm-premium-packages'); ?></label>
                &nbsp; <label><input type="checkbox" name="admin" value="1"> <?php _e('Site Admin','wpdm-premium-packages'); ?></label>
                &nbsp; <label><input type="checkbox" name="seller" value="1"> <?php _e('Seller','wpdm-premium-packages'); ?></label>
                &nbsp; <label><input type="checkbox" name="customer" value="1"> <?php _e('Customer','wpdm-premium-packages'); ?></label>
            </div>
        </div>
    </div>
</form>

<!-- order note template -->
<div class="modal fade" id="ontmodal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <strong><?php _e( "Order note templates", "wpdm-premium-packages" ); ?></strong>
                <div>
                    <a href="#" data-toggle="modal" data-target="#nontmodal" class="btn btn-success btn-xs"><?php echo Icons::get('plus-circle', 12); ?> <?php _e( "Add New", "download-manager" ); ?></a>
                    <button type="button" data-dismiss="modal" class="btn btn-secondary btn-xs">Close</button>
                </div>
            </div>

            <div class="modal-body" id="__wpdm_onts">
				<?php
                    /*
				$upload_dir = wp_upload_dir();
				$upload_dir = $upload_dir['basedir'];
				$nt_dir = $upload_dir.'/wpdmpp-note-templates/';
				if(!file_exists($nt_dir)) {
					mkdir( $nt_dir, 0755, true );
					\WPDM\__\FileSystem::blockHTTPAccess($nt_dir);
				}

				$custom_tags = scandir($nt_dir);
				$zx = 1;
				foreach ($custom_tags as $custom_tag){
					if(strstr($custom_tag, '.ont')) {
						$content = file_get_contents($nt_dir.$custom_tag);
						$custom_tag = str_replace(".ont", "", $custom_tag);
						?>
                        <div class="panel panel-default" style="margin-bottom: 10px" id="row_<?php echo $custom_tag; ?>">
                            <div class="panel-heading">
                                <button type="button" class="btn btn-xs btn-primary pull-right insert-ont" data-ont="#ont_<?php  echo  $zx; ?>">Insert</button>
								<?php echo $custom_tag; ?>
                            </div>
                            <div id="ont_<?php  echo $zx++; ?>" style="font-family: 'Courier', monospace;white-space: pre-wrap;padding: 0 15px;" readonly="readonly" class="panel-body"><?php echo ( WPDM\__\__::sanitize_var(stripslashes($content), 'kses')); ?></div>
                        </div>
						<?php
					}
				} */ ?>
                <div v-for="(template, id) in templates">
                    <div class="panel panel-default" style="margin-bottom: 10px">
                        <div class="panel-heading" style="display: flex;justify-content: space-between;">
			                <div>{{ template.name }}</div>
                            <div  style="display: flex;gap: 4px">
                                <button type="button" class="btn btn-xs btn-info ont-edit" :data-ont="id" :data-row="'#row_'+id"><?php echo Icons::get('pencil', 12); ?></button>
                                <button type="button" class="btn btn-xs btn-danger ont-delete" :data-ont="id" :data-row="'#row_'+id"><?php echo Icons::get('trash', 12); ?></button>
                                <button type="button" class="btn btn-xs btn-primary insert-ont" :data-ont="'#ont_'+id"><?php _e('Insert', WPDMPP_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <div :id="'ont_'+id" style="font-family: 'Courier', monospace;white-space: pre-wrap;padding: 0 15px;" readonly="readonly" class="panel-body">{{ template.content }}</div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="nontmodal" tabindex="-1" role="dialog" aria-labelledby="preview" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="newontform">
            <input type="hidden" name="id" id="_tid" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel"><?php _e( "New Template" , "download-manager" ); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" id="tag_name" name="ont[name]" class="form-control input-lg" placeholder="<?php echo __( "Template Name", "download-manager" ) ?>" />
                    </div>
                    <div class="form-group">
                        <textarea id="tag_value" placeholder="<?php echo __( "Order note template", "download-manager" ) ?>" class="form-control" style="height: 100px" name="ont[template]"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="newontformsubmit" style="width: 180px" class="btn btn-success btn-lg"><?php echo __( "Save Template", "download-manager" ) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    var __wpdm_onts = Vue.createApp({
        data() {
            return {
                templates: {}
            };
        }
    }).mount('#__wpdm_onts');
    jQuery(function($){

        let $body = $('body');
        const wpdmppOntApi = {
            base: '<?php echo esc_url_raw(rest_url('wpdmpp/v1/admin/order-note-templates')); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
        };

        function wpdmppOntNormalize(templates) {
            return templates && typeof templates === 'object' ? templates : {};
        }

        <?php if(version_compare(WPDM_VERSION, '6.7.0', '>')) { ?>
        $body.on('click', '#sgc', function () {
            WPDM.blockUI('#post-order-note');
            $.post(ajaxurl, {action: 'wpdm_aiassist', ainonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>', prompt: $('#order-note').val(), generate: 'grammarCheck'}, function (res) {
                $('#order-note').val(res.data)
                WPDM.unblockUI('#post-order-note');
            });
        });

        $body.on('click', '#art', function () {
            WPDM.blockUI('#post-order-note');
            $.post(ajaxurl, {action: 'wpdm_aiassist', ainonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>', prompt: $('#order-note').val(), generate: 'reArticulate'}, function (res) {
                $('#order-note').val(res.data)
                WPDM.unblockUI('#post-order-note');
            });
        });

        $body.on('click', '#air', function () {
            let _prompt = prompt('What is this about?', 'Write a thank you note for a new customer.');
            if(!_prompt) return;
            WPDM.blockUI('#post-order-note');
            $.post(ajaxurl, {action: 'wpdm_aiassist', ainonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>', prompt: _prompt, generate: 'aiReply'}, function (res) {
                $('#order-note').val(res.data)
                WPDM.unblockUI('#post-order-note');
            });
        });

        <?php } else { ?>
        $body.on('click', '#air, #art, #sgc', function () {
            $('#order-note').val('Only available with WPDM v6.7.1 or greater!')
        });
        <?php } ?>

        $.ajax({
            url: wpdmppOntApi.base,
            method: 'GET',
            headers: { 'X-WP-Nonce': wpdmppOntApi.nonce },
            success: function (res) {
                if (res && res.success && res.data) {
                    __wpdm_onts.templates = wpdmppOntNormalize(res.data.templates);
                }
            }
        });

        $body.on('click', '.insert-ont', function () {
            $('#order-note').val($($(this).data('ont')).html());
            $('#ontmodal').modal('hide');
        });

        let arow = '';
        $('#newontform').submit(function (e) {
            e.preventDefault();
            var obtnlbl = $('#newontformsubmit').html();
            $('#newontformsubmit').html('<?php echo Icons::spinner(14); ?>').attr('disabled', 'disabled');
            $.ajax({
                url: wpdmppOntApi.base,
                method: 'POST',
                headers: { 'X-WP-Nonce': wpdmppOntApi.nonce },
                data: {
                    id: $('#_tid').val(),
                    name: $('#tag_name').val(),
                    content: $('#tag_value').val()
                },
                success: function (response) {
                    $('#newontformsubmit').html(obtnlbl).removeAttr('disabled');
                    $(arow).hide();
                    if (response && response.success && response.data) {
                        __wpdm_onts.templates = wpdmppOntNormalize(response.data.templates);
                    }
                    $('#newontform')[0].reset();
                    $('#_tid').val('');
                    $('#nontmodal').modal('hide');
                },
                error: function () {
                    $('#newontformsubmit').html(obtnlbl).removeAttr('disabled');
                }
            });
        });
        $('body').on('click', '.ont-edit', function () {
            $('#nontmodal').modal('show');
            WPDM.blockUI('#newontform');
            arow = $(this).data('row');
            $.ajax({
                url: wpdmppOntApi.base + '/' + encodeURIComponent($(this).data('ont')),
                method: 'GET',
                headers: { 'X-WP-Nonce': wpdmppOntApi.nonce },
                success: function (response) {
                    if (response && response.success && response.data && response.data.template) {
                        var tpl = response.data.template;
                        $('#_tid').val(tpl.id);
                        $('#tag_name').val(tpl.name);
                        $('#tag_value').val(tpl.content);
                    }
                    WPDM.unblockUI('#newontform');
                },
                error: function () {
                    WPDM.unblockUI('#newontform');
                }
            });
        });
        $('body').on('click', '.ont-delete', function (e) {
            e.preventDefault();
            arow = $(this).data('row');
            if(!confirm('<?php echo esc_js(__( "Are you sure?", "download-manager" )); ?>')) return false;
            $.ajax({
                url: wpdmppOntApi.base + '/' + encodeURIComponent($(this).data('ont')),
                method: 'DELETE',
                headers: { 'X-WP-Nonce': wpdmppOntApi.nonce },
                success: function (response) {
                    $(arow).hide();
                    if (response && response.success && response.data) {
                        __wpdm_onts.templates = wpdmppOntNormalize(response.data.templates);
                    }
                }
            });
        });
    });

</script>

<script>
    jQuery(function($){
        $('#post-order-note').submit(function(e){
            e.preventDefault();
            var $form = $(this);
            var orderId = $form.data('order-id');
            var noteText = $('#order-note').val();
            if (!noteText.trim()) return;

            $('#add-note-button').html('<?php echo Icons::spinner(14); ?> <?php _e('Adding...','wpdm-premium-packages'); ?>');

            var files = [];
            $form.find('input[name="file[]"]').each(function(){ files.push($(this).val()); });

            $.ajax({
                url: '<?php echo esc_url(rest_url('wpdmpp/v1/admin/orders/')); ?>' + orderId + '/note',
                method: 'POST',
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                data: JSON.stringify({
                    note: noteText,
                    admin: $form.find('input[name="admin"]').is(':checked'),
                    seller: $form.find('input[name="seller"]').is(':checked'),
                    customer: $form.find('input[name="customer"]').is(':checked'),
                    file: files
                }),
                success: function(res){
                    $('#add-note-button').html('<?php echo Icons::get('plus-circle', 14); ?> <?php _e('Add Note','wpdm-premium-packages'); ?>');
                    if (res.success && res.data && res.data.html) {
                        $('#all-notes').append(res.data.html);
                        $('#order-note').val('');
                        $('#filelist').empty();
                    } else {
                        alert(res.message || 'Error!');
                    }
                },
                error: function(xhr){
                    $('#add-note-button').html('<?php echo Icons::get('plus-circle', 14); ?> <?php _e('Add Note','wpdm-premium-packages'); ?>');
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error!';
                    alert(msg);
                }
            });
        });
    });
</script>
