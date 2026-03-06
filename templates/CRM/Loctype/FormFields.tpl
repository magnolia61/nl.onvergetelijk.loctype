{* 1. Render de originele velden naar Smarty variabelen *}
{assign var="locHtml" value=$form.email_location_type_id.html}
{assign var="methodHtml" value=$form.email_selection_method.html}

{* 2. Bouw het popup element met placeholders *}
<div id="loctype-dialog-content" title="{ts}Email Location Configuration{/ts}" style="display:none;">
  <div class="crm-block crm-form-block">
    <table class="form-layout-compressed">
      <tr>
        <td class="label" style="width:40%;">{$form.email_location_type_id.label}</td>
        <td class="view-value" style="padding: 4px;">
            {$locHtml}
        </td>
      </tr>
      <tr id="loctype-method-row">
        <td class="label">{$form.email_selection_method.label}</td>
        <td class="view-value" style="padding: 4px;">
            {$methodHtml}
        </td>
      </tr>
    </table>
    
    <div id="loctype-description-box" class="description" style="margin-top:12px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 0.9em; line-height: 1.4em;">
      <strong>{ts}Selection logic:{/ts}</strong><br />
      <em>{ts}Prefer:{/ts}</em> {ts}This type if available, otherwise falls back to primary.{/ts}<br />
      <em>{ts}Exclude:{/ts}</em> {ts}Uses primary email, except if it is of this location type.{/ts}
    </div>
  </div>
</div>

<script type="text/javascript">
  CRM.$(function($) {
    var $modeSelect = $('#mode');
    var $mainForm   = $('form.CRM_Admin_Form_ScheduleReminders');
    
    if (!$modeSelect.length || !$mainForm.length) return;

    // Injecteer de moersleutel knop naast de Message Mode dropdown
    var wrenchBtn = '<a href="#" class="crm-hover-button loctype-config-btn" title="{ts}Configure Email Location{/ts}" style="display:none; margin-left:10px;"><i class="crm-i fa-wrench"></i></a>';
    $modeSelect.after(wrenchBtn);

    // Logica voor de modal
    $('.loctype-config-btn').on('click', function(e) {
      e.preventDefault();
      var $dialogContent = $('#loctype-dialog-content');
      
      $dialogContent.dialog({
        modal: true,
        width: 520, 
        height: 'auto',
        resizable: false,
        draggable: true,
        buttons: [
          {
            text: "{ts}Done{/ts}",
            icons: { primary: "ui-icon-check" },
            class: "crm-button-action",
            click: function() { $(this).dialog("close"); }
          }
        ],
        open: function() {
          var $dialog = $(this).closest('.ui-dialog');
          
          var $locSelect = $('#email_location_type_id');
          var $methodSelect = $('#email_selection_method');

          // Verwijder de verwarrende 'automatic' keuze uit de Method dropdown
          if ($methodSelect.find('option[value="automatic"]').length) {
              $methodSelect.find('option[value="automatic"]').remove();
              // Als hij per ongeluk nog op automatic stond, zet hem netjes op Prefer
              if (!$methodSelect.val() || $methodSelect.val() === 'automatic') {
                  $methodSelect.val('location_prefer');
              }
          }

          // Re-initialiseer Select2 met het dialog element als parent
          $dialogContent.find('select').each(function() {
            if ($(this).data('select2')) {
              $(this).select2('destroy');
            }
            $(this).crmSelect2({ 
                width: '100%',
                dropdownParent: $dialog
            });
          });

          // SLIMME UX LOGICA: Verberg methode en uitleg als er geen locatie is gekozen
          function updateUx() {
              if (!$locSelect.val()) { 
                  // Geen locatie gekozen = CiviCRM standaard gedrag
                  $('#loctype-method-row').hide();
                  $('#loctype-description-box').hide();
              } else {
                  // Locatie gekozen = Toon de methode keuzes
                  $('#loctype-method-row').show();
                  $('#loctype-description-box').show();
              }
          }
          
          $locSelect.on('change', updateUx);
          updateUx(); // Direct runnen bij het openen van de popup
        }
      });
    });

    // Zichtbaarheid van de moersleutel knop regelen
    function toggleWrench() {
      var isEmail = ($modeSelect.val() === 'Email');
      $('.loctype-config-btn').toggle(isEmail);
    }
    $modeSelect.on('change', toggleWrench);
    toggleWrench();

    // ========================================================================
    // OPSLAAN LOGICA
    // ========================================================================
    $mainForm.on('submit', function() {
      var locVal = $('#email_location_type_id').val() || '';
      
      // Als er geen locatie is, forceren we de methode in de database onzichtbaar terug naar 'automatic'
      var methodVal = locVal ? ($('#email_selection_method').val() || 'location_prefer') : 'automatic';

      // Verwijder oude hidden fields en voeg de actuele waarden toe aan het hoofdformulier
      $('.loctype-hidden-sync').remove();
      $mainForm.append('<input type="hidden" class="loctype-hidden-sync" name="email_location_type_id" value="' + locVal + '">');
      $mainForm.append('<input type="hidden" class="loctype-hidden-sync" name="email_selection_method" value="' + methodVal + '">');
    });

  });
</script>

<style type="text/css">
  /* Voorkom scrollbalk in popup */
  #loctype-dialog-content { overflow-x: hidden !important; }
</style>
