<script type="text/javascript">
  CRM.$(function($) {
    var $modeSelect = $('#mode');
    if (!$modeSelect.length) return;

    // Native CiviCRM wrench button injection
    var wrenchBtn = '<a href="#" class="crm-hover-button loctype-popup-link" title="{ts}Configure Email Location{/ts}" style="display:none; margin-left:10px;"><i class="crm-i fa-wrench"></i></a>';
    $modeSelect.after(wrenchBtn);

    $('.loctype-popup-link').on('click', function(e) {
      e.preventDefault();
      var reminderID = "{$loctype_reminder_id}";
      
      // We open a native CiviCRM popup. 
      // This is exactly how Bulk Mail settings work.
      var url = CRM.url('civicrm/loctype/settings', {
        reset: 1,
        id: reminderID,
        snippet: 4 // This loads only the content part
      });

      CRM.loadForm(url).done(function(data) {
        // When the popup saves, we can optionally refresh or notify
        CRM.statusMsg('{ts}Location settings updated{/ts}');
      });
    });

    function toggleWrench() {
      $('.loctype-popup-link').toggle($modeSelect.val() === 'Email');
    }

    $modeSelect.on('change', toggleWrench);
    toggleWrench();
  });
</script>
