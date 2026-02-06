<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
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
                        <a href="<?php echo home_url("/?oid=".$order->order_id."&_atcdl=".$aid); ?>" style="margin-left: 10px"><i class="fa fa-paperclip"></i> <?php echo $file; ?></a> &nbsp;
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="panel-footer text-right">
                <small><em><i class="fas fa-pencil-alt"></i> <?php echo $order_note['by']; ?> &nbsp; <i class="fa fa-clock-o"></i> <?php echo wp_date(get_option('date_format') . " h:i", $time); ?></em></small>
                <div class="pull-left"><small><em><?php if($copy!='') echo "Copy sent to ".$copy; ?></em></small></div>
            </div>
        </div>
    <?php
    }
}
?>
</div>
<form method="post" id="post-order-note">
    <?php wp_nonce_field(WPDM_PUB_NONCE, 'wpdmppasyncrequest'); ?>
    <input type="hidden" name="execute" value="AddNote" />
    <input type="hidden" name="order_id" value="<?php echo $order->order_id; ?>" />
    <div class="panel panel-default dashboard-panel">
        <textarea id="order-note" name="note" class="form-control" style="border: 0;box-shadow: none;min-height: 90px;max-width: 100%;min-width: 100%;padding: 10px"></textarea>

        <div id="wpdm-upload-ui" class="panel-footer image-selector-panel">
            <div id="filelist" class="pull-right"></div>
            <div id="wpdm-drag-drop-area">

                <button id="wpdm-browse-button" style="text-transform: unset;letter-spacing: 1px" type="button" class="btn btn-xs btn-info"><i class="fas fa-file-upload"></i> <?php _e("Select File", "download-manager");  ?></button>
                <div class="progress" id="wmprogressbar" style="width: 111px;height: 20px !important;border-radius: 2px !important;margin: 0;position: relative;background: #0d406799;display: none;box-shadow: none">
                    <div id="wmprogress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;line-height: 20px;background-color: #007bff"></div>
                    <div class="fetfont" style="font-size:8px;position: absolute;line-height: 20px;height: 20px;width: 100%;z-index: 999;text-align: center;color: #ffffff;letter-spacing: 1px">UPLOADING... <span id="wmloaded">0</span>%</div>
                </div>



                <?php

                $plupload_init = array(
                    'runtimes'            => 'html5,silverlight,flash,html4',
                    'browse_button'       => 'wpdm-browse-button',
                    'container'           => 'wpdm-upload-ui',
                    'drop_element'        => 'wpdm-drag-drop-area',
                    'file_data_name'      => 'attach_file',
                    'multiple_queues'     => false,
                    'url'                 => admin_url('admin-ajax.php'),
                    'flash_swf_url'       => includes_url('js/plupload/plupload.flash.swf'),
                    'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
                    'filters'             => array(array('title' => __('Allowed Files'), 'extensions' => get_option('__wpdm_allowed_file_types', 'png,pdf,jpg,txt'))),
                    'multipart'           => true,
                    'urlstream_upload'    => true,


                    'multipart_params'    => array(
                        '_ajax_nonce' => wp_create_nonce(NONCE_KEY),
                        'action'      => 'wpdm_frontend_file_upload',
                        'section'     => 'wpdm_order_note',
                    ),
                );

                $plupload_init['max_file_size'] = wp_max_upload_size().'b';


                $plupload_init = apply_filters('plupload_init', $plupload_init); ?>

                <script type="text/javascript">



                    jQuery(function($){


                        var uploader = new plupload.Uploader(<?php echo json_encode($plupload_init); ?>);

                        uploader.bind('Init', function(up){
                            var uploaddiv = $('#wpdm-upload-ui');

                            if(up.features.dragdrop){
                                uploaddiv.addClass('drag-drop');
                                $('#drag-drop-area')
                                    .bind('dragover.wp-uploader', function(){ uploaddiv.addClass('drag-over'); })
                                    .bind('dragleave.wp-uploader, drop.wp-uploader', function(){ uploaddiv.removeClass('drag-over'); });

                            }else{
                                uploaddiv.removeClass('drag-drop');
                                $('#drag-drop-area').unbind('.wp-uploader');
                            }
                        });

                        uploader.init();

                        uploader.bind('Error', function(uploader, error){
                            WPDM.bootAlert('Error', error.message, 400);
                            $('#wmprogressbar').hide();
                            $('#wpdm-browse-button').show();
                        });


                        uploader.bind('FilesAdded', function(up, files){
                            /*var hundredmb = 100 * 1024 * 1024, max = parseInt(up.settings.max_file_size, 10); */

                            $('#wpdm-browse-button').hide();
                            $('#wmprogressbar').show();

                            plupload.each(files, function(file){
                                $('#wmprogress').css('width', file.percent+"%");
                                $('#wmloaded').html(file.percent);
                                jQuery('#filelist').append(
                                    '<div class="file pull-left" id="' + file.id + '"><b>' +
                                    file.name + '</b> (<span>' + plupload.formatSize(0) + '</span>/' + plupload.formatSize(file.size) + ') </div>');
                            });



                            up.refresh();
                            up.start();
                        });

                        uploader.bind('UploadProgress', function(up, file) {
                            $('#wmprogress').css('width', file.percent+"%");
                            $('#wmloaded').html(file.percent);
                            jQuery('#' + file.id + " .fileprogress").width(file.percent + "%");
                            jQuery('#' + file.id + " span").html(plupload.formatSize(parseInt(file.size * file.percent / 100)));
                        });


                        uploader.bind('FileUploaded', function(up, file, data) {
                            console.log(data);
                            data = data.response;
                            data = data.split("|||");
                            console.log(data);
                            $('#wmprogressbar').hide();
                            $('#wpdm-browse-button').show();

                            jQuery('#' + file.id ).remove();
                            var d = new Date();
                            var ID = d.getTime();
                            var filename = data[1];
                            var fileinfo = "<span id='file_"+ID+"' class='atcf' ><a href='#' rel='#file_"+ID+"' class='del-file text-danger'><i class='fa fa-times'></i></a> &nbsp; <input type='hidden' name='file[]' value='"+filename+"' />"+filename+"</span>";
                            jQuery('#filelist').prepend(fileinfo);


                        });


                    });



                </script>

                <div class="clear"></div>

            </div>
        </div>
        <div class="panel-footer text-right">
            <button type='button' id="sgc" class='btn btn-sm btn-secondary ttip' title="<?php _e('Spell and Grammar Check', WPDMPP_TEXT_DOMAIN); ?>"><i class="fa-solid fa-spell-check"></i></button>
            <button type='button' id="air" class='btn btn-sm btn-secondary ttip' title="<?php _e('Reply with AI', WPDMPP_TEXT_DOMAIN); ?>"><i class="fa-solid fa-robot"></i></button>
            <button type='button' id="art" class='btn btn-sm btn-secondary ttip' title="<?php _e('Re-articulate', WPDMPP_TEXT_DOMAIN); ?>"><i class="fa-solid fa-wand-magic-sparkles"></i></button>

            <button data-toggle='modal' data-target='#ontmodal' type='button' class='btn btn-sm btn-info'><?php _e('Templates', WPDMPP_TEXT_DOMAIN); ?></button>
            <button class="btn btn-primary btn-sm" id="add-note-button" type="submit"><i class="fa fa-plus-circle"></i> <?php _e('Add Note','wpdm-premium-packages'); ?></button>
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
                    <a href="#" data-toggle="modal" data-target="#nontmodal" class="btn btn-success btn-xs"><i class="fa fa-plus-circle"></i> <?php _e( "Add New", "download-manager" ); ?></a>
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
                                <button type="button" class="btn btn-xs btn-info ont-edit" :data-ont="id" :data-row="'#row_'+id"><i class="fa fa-pencil m-0"></i></button>
                                <button type="button" class="btn btn-xs btn-danger ont-delete" :data-ont="id" :data-row="'#row_'+id"><i class="fa fa-trash m-0"></i></button>
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
            <input type="hidden" name="action" value="wpdm_save_order_note_template">
            <input type="hidden" name="id" id="_tid" value="">
            <input type="hidden" name="__ontxnonce" value="<?php echo wp_create_nonce(NONCE_KEY); ?>">
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
                templates: []
            };
        }
    }).mount('#__wpdm_onts');
    jQuery(function($){

        let $body = $('body');
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

        $.get(ajaxurl, {action: 'wpdm_get_order_note_templates', __ontgnonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>'}, function (res) {
            __wpdm_onts.templates = res;
        });

        $body.on('click', '.insert-ont', function () {
            $('#order-note').val($($(this).data('ont')).html());
            $('#ontmodal').modal('hide');
        });

        let arow = '';
        $('#newontform').submit(function (e) {
            e.preventDefault();
            var obtnlbl = $('#newontformsubmit').html();
            $('#newontformsubmit').html("<i class='fa fa-sun fa-spin'></i>").attr('disabled', 'disabled');
            $(this).ajaxSubmit({
                url: ajaxurl,
                resetForm: true,
                success: function (response) {
                    $('#newontformsubmit').html(obtnlbl).removeAttr('disabled');
                    $(arow).hide();
                    __wpdm_onts.templates = response;
                    $('#nontmodal').modal('hide');
                }
            });
        });
        $('body').on('click', '.ont-edit', function () {
            $('#nontmodal').modal('show');
            WPDM.blockUI('#newontform');
            arow = $(this).data('row');
            $.get(ajaxurl, {id: $(this).data('ont'), action: 'wpdm_edit_order_note_template'}, function (response) {
                $('#_tid').val(response.id);
                $('#tag_name').val(response.name);
                $('#tag_value').val(response.content);
                WPDM.unblockUI('#newontform');
            })
        });
        $('body').on('click', '.ont-delete', function (e) {
            e.preventDefault();
            arow = $(this).data('row');
            if(!confirm('<?php echo __( "Are you sure?", "download-manager" ); ?>')) return false;
            var tag = $(this).data('tag');
            $.get(ajaxurl, {id: $(this).data('ont'), action: 'wpdm_delete_order_note_template'}, function (response) {
                $(arow).hide();
                __wpdm_onts.templates = response;
            })
        });
    });

</script>

<script>
    jQuery(function($){
        $('#post-order-note').submit(function(){
            $('#add-note-button').html('<i class="fa fa-spinner fa-spin"></i> <?php _e('Adding...','wpdm-premium-packages'); ?>');
            $(this).ajaxSubmit({
                url: '<?php echo admin_url('/admin-ajax.php?action=wpdmpp_async_request'); ?>',
                success: function(res){
                    $('#add-note-button').html('<i class="fa fa-plus-circle"></i> <?php _e('Add Note','wpdm-premium-packages'); ?>');
                    if(res!='error'){
                        $('#all-notes').append(res);
                        $("#order-note").val("");
                    }
                    else
                    alert('Error!');
                }
            });
            return false;
        });
    });
</script>
