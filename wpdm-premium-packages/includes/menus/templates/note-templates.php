<?php
/**
 * Base: wpdmpro
 * Developer: shahjada
 * Team: W3 Eden
 * Date: 22/5/20 08:20
 */
if(!defined("ABSPATH")) die();
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <a href="#" data-toggle="modal" data-target="#newtagmodal" class="btn btn-success pull-right btn-xs"><i class="fa fa-plus-circle"></i> <?php _e( "Add New Template", "download-manager" ); ?></a>
        <?= esc_attr__( 'Order Note Templates', WPDM_TEXT_DOMAIN ); ?>
    </div>
    <table class="table table-striped" id="tagstable">
        <thead>
        <tr>
            <th><?php _e( "Name", "download-manager" ) ?></th>
            <th><?php _e( "Template", "download-manager" ) ?></th>
            <th><?php _e( "Action", "download-manager" ) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['basedir'];
        $nt_dir = $upload_dir.'/wpdmpp-note-templates/';
        if(!file_exists($nt_dir)) {
	        mkdir( $nt_dir, 0755, true );
            \WPDM\__\FileSystem::blockHTTPAccess($nt_dir);
        }

        $custom_tags = scandir($nt_dir);
        foreach ($custom_tags as $ind => $custom_tag){
            if(strstr($custom_tag, '.ont')) {
                $content = file_get_contents($nt_dir.$custom_tag);
                $custom_tag = str_replace(".ont", "", $custom_tag);
                ?>
                <tr id="row_<?php echo $ind; ?>">
                    <td><nobr><?php echo $custom_tag; ?></nobr></td>
                    <td style="white-space: pre-wrap;font-family: Courier"><?php echo htmlspecialchars(stripslashes($content)); ?></td>
                    <td style="width: 220px">
                        <a href="#" class="btn btn-info ont-edit" data-tag="<?php echo $custom_tag; ?>" data-row="#row_<?php echo $ind; ?>"><?php _e( "Edit", "download-manager" ); ?></a>
                        <a href="#" class="btn btn-danger ont-delete" data-tag="<?php echo $custom_tag; ?>" data-row="#row_<?php echo $ind; ?>"><?php _e( "Delete", "download-manager" ); ?></a>
                    </td>
                </tr>
                <?php
            }
        } ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="newtagmodal" tabindex="-1" role="dialog" aria-labelledby="preview" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="newontform">
            <input type="hidden" name="action" value="wpdm_save_order_note_template">
            <input type="hidden" name="__ontxnonce" value="<?php echo wp_create_nonce(NONCE_KEY); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel"><?php _e( "New Template" , "download-manager" ); ?></h4>
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

    jQuery(function($){
       
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
                    $('#tagstable tbody').append(
                        '                <tr id="row_'+response.name+'">\n' +
                        '                    <td><nobr>'+response.name+'</nobr></td>\n' +
                        '                    <td style="white-space: pre-wrap;font-family: Courier">'+response.value+'</td>\n' +
                        '                    <td style="width: 220px">\n' +
                        '                        <a href="#" class="btn btn-info ont-edit" data-tag="'+response.name+'"><?php _e("Edit", "download-manager"); ?></a>\n' +
                        '                        <a href="#" class="btn btn-danger ont-delete" data-tag="'+response.name+'"><?php _e("Delete", "download-manager"); ?></a>\n' +
                        '                    </td>\n' +
                        '                </tr>'
                    );
                    $('#newtagmodal').modal('hide');
                }
            });
        });
        $('body').on('click', '.ont-edit', function () {
            $('#newtagmodal').modal('show');
            WPDM.blockUI('#newontform');
            arow = $(this).data('row');
            $.get(ajaxurl, {name: $(this).data('tag'), action: 'wpdm_edit_order_note_template'}, function (response) {
                $('#tag_name').val(response.name);
                $('#tag_value').val(response.template);
                WPDM.unblockUI('#newontform');
            })
        });
        $('body').on('click', '.ont-delete', function (e) {
            e.preventDefault();
            arow = $(this).data('row');
            if(!confirm('<?php echo __( "Are you sure?", "download-manager" ); ?>')) return false;
            var tag = $(this).data('tag');
            $.get(ajaxurl, {tag: $(this).data('tag'), action: 'wpdm_delete_order_note_template'}, function (response) {
                $(arow).hide();
            })
        });
    });

</script>