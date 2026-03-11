{* Wrapper om makkelijk op te kunnen schonen bij AJAX loads *}
<div class="loctype-wrapper">
    
    {* Bouw het popup element. We gebruiken classes ipv IDs voor de inputs om ID-conflicten te voorkomen *}
    <div class="loctype-dialog-content" title="{ts}Email Location Configuration{/ts}" style="display:none;">
      <div class="crm-block crm-form-block">
        <table class="form-layout-compressed">
          <tr>
            <td class="label" style="width:40%;">{$form.email_location_type_id.label}</td>
            <td class="view-value" style="padding: 4px;">
                {$form.email_location_type_id.html}
            </td>
          </tr>
          <tr class="loctype-method-row">
            <td class="label">{$form.email_selection_method.label}</td>
            <td class="view-value" style="padding: 4px;">
                {$form.email_selection_method.html}
            </td>
          </tr>
        </table>
        
        <div class="loctype-description-box description" style="margin-top:12px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 0.9em;">
          <strong>{ts}Selection logic:{/ts}</strong><br />
          <em>{ts}Prefer:{/ts}</em> {ts}This type if available, otherwise falls back to primary.{/ts}<br />
          <em>{ts}Exclude:{/ts}</em> {ts}Uses primary email, except if it is of this location type.{/ts}
        </div>
      </div>
    </div>

    <script type="text/javascript">
      CRM.$(function($) {
        // --- 1. OPSCHONEN (Cruciaal voor AJAX flow) ---
        // jQuery UI verplaatst dialoogvensters naar het einde van de <body>. 
        // We verwijderen alle oude dialoog-containers van vorige edits.
        $('.ui-dialog-content.loctype-dialog-content').not(':last').each(function() {
            $(this).dialog('destroy').remove();
        });
        
        // Verwijder oude wrappers (van de pagina zelf)
        if ($('.loctype-wrapper').length > 1) {
            $('.loctype-wrapper').not(':last').remove();
        }

        // Bepaal de context van het huidige formulier
        var $mainForm   = $('form.CRM_Admin_Form_ScheduleReminders').last();
        var $modeSelect = $mainForm.find('#mode');
        
        if (!$modeSelect.length || !$mainForm.length) return;

        // --- 2. WRENCH KNOP INJECTIE ---
        $mainForm.find('.loctype-config-btn').remove();
        var wrenchBtn = '<a href="#" class="crm-hover-button loctype-config-btn" title="{ts}Configure Email Location{/ts}" style="display:none; margin-left:10px;"><i class="crm-i fa-wrench"></i></a>';
        $modeSelect.after(wrenchBtn);

        // --- 3. DIALOOG LOGICA ---
        $mainForm.on('click', '.loctype-config-btn', function(e) {
          e.preventDefault();
          
          // Zoek het dialoogvenster dat bij deze specifieke wrapper hoort
          var $dialogContent = $(this).closest('.loctype-wrapper').find('.loctype-dialog-content');
          
          $dialogContent.dialog({
            modal: true,
            width: 520, 
            height: 'auto',
            resizable: false,
            buttons: [{
                text: "{ts}Done{/ts}",
                icons: { primary: "ui-icon-check" },
                class: "crm-button-action",
                click: function() { $(this).dialog("close"); }
            }],
            open: function() {
              var $dialog = $(this);
              var $locSelect = $dialog.find('select[name="email_location_type_id"]');
              var $methodSelect = $dialog.find('select[name="email_selection_method"]');

              // Synchroniseer met huidige verborgen velden in het formulier (indien aanwezig)
              // Dit voorkomt dat we oude waarden uit de cache zien.
              var currentLoc = $mainForm.find('input[name="email_location_type_id"]').val();
              var currentMethod = $mainForm.find('input[name="email_selection_method"]').val();
              
              if (currentLoc !== undefined) $locSelect.val(currentLoc);
              if (currentMethod !== undefined) $methodSelect.val(currentMethod);

              // Opschonen 'automatic' optie
              $methodSelect.find('option[value="automatic"]').remove();
              if (!$methodSelect.val() || $methodSelect.val() === 'automatic') {
                  $methodSelect.val('location_prefer');
              }

              // Re-init Select2 binnen de dialoog
              $dialog.find('select').crmSelect2({ width: '100%' });

              function updateUx() {
                  var hasLoc = !!$locSelect.val();
                  $dialog.find('.loctype-method-row').toggle(hasLoc);
                  $dialog.find('.loctype-description-box').toggle(hasLoc);
              }
              
              $locSelect.on('change', updateUx);
              updateUx();
            }
          });
        });

        // Toggle wrench zichtbaarheid
        function toggleWrench() {
          $mainForm.find('.loctype-config-btn').toggle($modeSelect.val() === 'Email');
        }
        $modeSelect.on('change', toggleWrench);
        toggleWrench();

        // --- 4. OPSLAAN SYNCHRONISATIE ---
        $mainForm.on('submit', function() {
          var $dialog     = $(this).closest('.loctype-wrapper').find('.loctype-dialog-content');
          
          // Als het dialoogvenster nog nooit geopend is, staan de waarden in de standaard Smarty render
          var locVal      = $dialog.find('select[name="email_location_type_id"]').val() || '';
          var methodVal   = locVal ? ($dialog.find('select[name="email_selection_method"]').val() || 'location_prefer') : 'automatic';

          // Verwijder oude hidden fields binnen DIT formulier
          $(this).find('.loctype-hidden-sync').remove();
          $(this).append('<input type="hidden" class="loctype-hidden-sync" name="email_location_type_id" value="' + locVal + '">');
          $(this).append('<input type="hidden" class="loctype-hidden-sync" name="email_selection_method" value="' + methodVal + '">');
        });
      });
    </script>
</div>

<style type="text/css">
  .loctype-dialog-content { overflow-x: hidden !important; }
  .loctype-config-btn { vertical-align: middle; cursor: pointer; }
</style>