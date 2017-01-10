jQuery(function ($) {
    var $box = $(document.getElementById('cider_admin_repeat_group_repeat'));
    var replaceTitles = function () {
        $box.find('.cmb-group-title').each(function () {
            var $this = $(this);
            var txt = $this.next().find('[.cmbhandle-title]').val();
            var rowindex;
            if (!txt) {
                txt = $box.find('[data-grouptitle]').data('grouptitle');
                if (txt) {
                    rowindex = $this.parents('[data-iterator]').data('iterator');
                    txt = txt.replace('{#}', ( rowindex + 1 ));
                }
            }
            if (txt) {
                $this.text(txt);
            }
        });
    };
    var replaceOnKeyUp = function (evt) {
        var $this = $(evt.target);
        var id = 'title';
        if (evt.target.id.indexOf(id, evt.target.id.length - id.length) !== -1) {
            console.log('val', $this.val());
            $this.parents('.cmb-row.cmb-repeatable-grouping').find('.cmb-group-title').text($this.val());
        }
    };
    $box
        .on('cmb2_add_row cmb2_shift_rows_complete', function (evt) {
            replaceTitles();
        })
        .on('keyup', replaceOnKeyUp);
    replaceTitles();
    // Hide the first generated empty item, add row and remove row
    $('#cider_repeat_group_repeat').find('.cmb-add-row,button.cmb-remove-group-row').hide();
});
