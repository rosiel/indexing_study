/**
 * @file
 * Track antecedents in the consensus form.
 *
 * Adds the selected terms to the antecedent fields on the paragraph.
 */

(function ($, Drupal, once) {
  Drupal.behaviors.IndexingStudyBehaviour = {
    attach: function (context, settings) {


      function populateAntecedents() {

        // Get the values to insert from the checkboxes
        $subjects1 = $('#edit-reviewer-1 input:checked');
        $subjects2 = $('#edit-reviewer-2 input:checked');
        // Clear out existing values TODO
        $antecedents_1_fields = $(this).parent().parent().parent().find('.field--name-field-reviewer-1-antecedents').find('input.form-text');
        $antecedents_2_fields = $(this).parent().parent().parent().find('.field--name-field-reviewer-2-antecedents').find('input.form-text');
        if (!$(this).val()) {
          $antecedents_1_fields.each( function ( index ) {
            $(this).val('');
          })
          $antecedents_2_fields.each( function ( index ) {
            $(this).val('');
          })
          return;
        }
        // Make enough slots to handle the subjects
        if ($antecedents_1_fields.length < $subjects1.length) {
          document.activeElement.classList.add('indexing-study-focus-element');
          $(this).parent().parent().parent().find('.field--name-field-reviewer-1-antecedents').find('input.field-add-more-submit').trigger('mousedown');
          return;
        }
        if ($antecedents_2_fields.length < $subjects2.length) {
          document.activeElement.classList.add('indexing-study-focus-element');
          $(this).parent().parent().parent().find('.field--name-field-reviewer-2-antecedents').find('input.field-add-more-submit').trigger('mousedown');
          return;
        }

        // Write the values
        $subjects1.each(function ( index ) {
          $antecedents_1_fields[index].value = $(this).val();
        })
        $subjects2.each(function ( index ) {
          $antecedents_2_fields[index].value = $(this).val();
        })

        // Clear the subjects checkboxes
        $subjects1.each(function ( index ) {
          $(this).prop('checked', false);
          $(this).next().css('color', 'grey');
        })
        $subjects2.each(function ( index ) {
          $(this).prop('checked', false)
          $(this).next().css('color', 'grey');
        })
      }

      const $elements = $(once('indexingStudy', '.consensus-topic .field--name-field-topic input'));
      $elements.each(triggerPopulateAntecedents);

      $(document).ajaxComplete(function() {
        $elementToFocus = $(this).find('.indexing-study-focus-element')
          // Set the focus to the target element (e.g., the new input field).
          // The ':last' pseudo-selector helps find the newest input in the list.
        if ( $elementToFocus && $elementToFocus.length) {
          setTimeout(function() {
            $elementToFocus.focus();
            $elementToFocus.removeClass('indexing-study-focus-element');
            populateAntecedents.call($elementToFocus);

          }, 10);
        }

      });
    }
  };
  function triggerPopulateAntecedents(index, value) {
    $(this).on('keyup', populateAntecedents);
  }



})(jQuery, Drupal, once);
