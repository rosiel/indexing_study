/**
 * @file
 * Track antecedents in the consensus form.
 *
 * Adds the selected terms to the antecedent fields on the paragraph.
 */

(function ($, Drupal, once) {
  Drupal.behaviors.IndexingStudyBehaviour = {
    attach: function (context, settings) {

      function populateAntecedents( $paragraph ) {

        $subjects1 = $('#edit-reviewer-1 input:checked');
        $subjects2 = $('#edit-reviewer-2 input:checked');

        $antecedents_1_fields = $paragraph.find('.field--name-field-reviewer-1-antecedents').find('input.form-text');
        $antecedents_2_fields = $paragraph.find('.field--name-field-reviewer-2-antecedents').find('input.form-text');

        // Make enough slots to handle the subjects
        if ($antecedents_1_fields.length < $subjects1.length) {
          document.activeElement.classList.add('indexing-study-focus-element');
          $paragraph.find('.field--name-field-reviewer-1-antecedents').find('input.field-add-more-submit').trigger('mousedown');
          return;
        }
        if ($antecedents_2_fields.length < $subjects2.length) {
          document.activeElement.classList.add('indexing-study-focus-element');
          $paragraph.find('.field--name-field-reviewer-2-antecedents').find('input.field-add-more-submit').trigger('mousedown');
          return;
        }

        // Write the antecedents values
        $antecedents_1_fields.each(function(index) {
          if ($subjects1[index]) {
            $(this).val($subjects1[index].value).addClass('js-populated');
          }
        });
        $antecedents_2_fields.each(function(index) {
          if ($subjects2[index]) {
            $(this).val($subjects2[index].value).addClass('js-populated');
          }
        });

        // Clear the subjects checkboxes and mark them used.
        $subjects1.each(function ( index ) {
          $(this).prop('checked', false);
          $(this).next().css('color', 'darkgrey');
        })
        $subjects2.each(function ( index ) {
          $(this).prop('checked', false)
          $(this).next().css('color', 'darkgray');
        })

        // Update display
        updateParagraphDisplay($paragraph);
      }

      function clearAntecedents($paragraph) {
        $paragraph.find('input.form-element--type-text').val('');
        updateParagraphDisplay($paragraph);

        // remove styling from checkboxes
        $('[id^="edit-reviewer-"] input').each(function() {
          var $checkbox = $(this);
          var isUsedElsewhere = false;

          // Check if this checkbox value is used in any other paragraph
          $('.paragraphs-subform', context).each(function() {
            var $otherPara = $(this);
            if ($otherPara[0] !== $paragraph[0]) {
              $otherPara.find('.paragraph-fields-wrapper input.form-element--type-text').each(function () {
                if ($(this).val() === $checkbox.val()) {
                  isUsedElsewhere = true;
                }
              })
            }
          });
          if (!isUsedElsewhere) {
            $checkbox.next().css('color','black');
          }
        });


        $subjects2 = $('#edit-reviewer-2 input:checked');
      }

      function updateParagraphDisplay($paragraph) {
        var $display = $paragraph.find('.antecedents-display');
        if ($display.length === 0) {
          $display = $('<div class="antecedents-display"></div>');
          $paragraph.find('.field--name-field-topic').after($display);
        }

        $display.empty();

        var r1Values = [];
        var r2Values = [];

        $paragraph.find('.field--name-field-reviewer-1-antecedents input.form-element--type-text').each(function() {
          var val = $(this).val();
          if (val) r1Values.push(val);
        });

        $paragraph.find('.field--name-field-reviewer-2-antecedents input.form-element--type-text').each(function() {
          var val = $(this).val();
          if (val) r2Values.push(val);
        });

        if (r1Values.length > 0) {
          $display.append('<div class="reviewer-antecedents"><strong>Reviewer 1:</strong> ' + r1Values.join(', ') + '</div>');
        }
        if (r2Values.length > 0) {
          $display.append('<div class="reviewer-antecedents"><strong>Reviewer 2:</strong> ' + r2Values.join(', ') + '</div>');
        }
      }

      // Hide antecedents fields
      $(once('hide-antecedents', '.paragraphs-subform')).each(function() {
        var $paragraph = $(this);
        // Wrap fields for easier hiding
        if ($paragraph.find('.paragraph-fields-wrapper').length === 0) {
          $paragraph.find('[id*="reviewer-1-antecedents-wrapper"], [id*="reviewer-2-antecedents-wrapper"]').wrapAll('<div class="paragraph-fields-wrapper" style="display: none;"></div>');
        }
      })

      // Set up the topic field to listen for keyup.
      $(once('indexingStudy', '.consensus-topic .field--name-field-topic input')).each( function() {
        $(this).on('keyup', function () {
          var $topic = $(this);
          var $paragraph = $topic.closest('.paragraphs-subform');
          populateAntecedents($paragraph);
        })
      });


      $(document).ajaxComplete(function() {
        $elementToFocus = $(this).find('.indexing-study-focus-element')
          // Set the focus to the target element (e.g., the new input field).
          // The ':last' pseudo-selector helps find the newest input in the list.
        if ( $elementToFocus && $elementToFocus.length) {
          setTimeout(function() {
            $elementToFocus.focus();
            $elementToFocus.removeClass('indexing-study-focus-element');
            $paragraph = $elementToFocus.closest('.paragraphs-subform');
            populateAntecedents($paragraph);

          }, 10);
        }
        $(this).find('.paragraphs-subform').each(function() {
          updateParagraphDisplay($(this));
        });

      });

      // Add CSS for used checkboxes
      if ($('#reviewer-subjects-styles').length === 0) {
        $('<style id="reviewer-subjects-styles">' +
          '.antecedents-display { margin: 10px 0; padding: 10px; background: #f5f5f5; border-left: 3px solid #0074bd; }' +
          '.reviewer-antecedents { margin: 5px 0; }' +
          '</style>').appendTo('head');
      }
    }
  };

})(jQuery, Drupal, once);
