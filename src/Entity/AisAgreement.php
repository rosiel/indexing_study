<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\indexing_study\Entity\AisConclusion;

class AisAgreement extends AbstractAisNode implements AisAgreementInterface
{

  public function getDependents(): array
  {
    return $this->computeDependents($this->config->get('conclusion.bundle'),
      $this->config->get('conclusion.agreement_field'));
  }

  public function getAgreementAssignment(): AisAgreementAssignmentInterface|NULL
  {
    $assignment = $this->get($this->config()->get('agreement.agreement_assignment_field'))->referencedEntities();
    if (count($assignment) > 0) {
      return $assignment[0];
    }
    return NULL;
  }

  public function getDocument(): AisDocumentInterface {
    return $this->get($this->config()->get('agreement.document_field'))->referencedEntities()[0];
  }

  public function getConsensusSubjectsRepresented(): bool {
    return $this->get($this->config()->get('agreement.consensus_subjects_represented'))->getValue()[0]['value'];

  }

  public function getSibling(): AisAgreementInterface|false
  {
    $document_field = $this->config()->get('agreement.document_field');
    $storage = $this->entityTypeManager()->getStorage('node');
    $other_agreements = $storage->getQuery()
      ->accessCheck(false)
      ->condition('type', $this->bundle())
      ->condition($document_field, $this->getDocument()->id())
      ->condition('nid', $this->id(), '!=')
      ->execute();
    if (count($other_agreements) > 1) {
      \Drupal::logger('indexing_study')->notice("More than two agreement nodes exist for document {$this->getDocument()->id()}.");
    }
    if (count($other_agreements) > 0) {

      $other_agreement = $storage->load(array_pop($other_agreements));
      if ($other_agreement instanceof AisAgreementInterface) {
        return $other_agreement;
      }
    }
    return false;
  }

  public function needsConclusion(): bool {
    $dependents = $this->getDependents();
    $sibling = $this->getSibling();
    return ((bool)$sibling and !(bool)$dependents);
  }

  public function generateConclusion(): void
  {
    // Don't generate a conclusion if not needed.
    if (!$this->needsConclusion()) return;

    $agreement1 = $this->getSibling();
    $agreement2 = $this;
    $document = $this->getDocument();
    $agreement_between_agreements = ($agreement1->getConsensusSubjectsRepresented() == $agreement2->getConsensusSubjectsRepresented());
    $conclusion = AisConclusion::create([
      'type' => $this->config()->get('conclusion.bundle'),
      'title' => "Conclusion for document {$document->id()}",
    ]);
    $conclusion->set($this->config()->get('conclusion.document_field'), ['target_id' => $document->id()]);
    $conclusion->set($this->config()->get('conclusion.agreement_field'), [
      ['target_id' => $agreement1->id()],
      ['target_id' => $agreement2->id()],
    ]);
    $conclusion->set($this->config()->get('conclusion.agreement_agreement_field'), ['value' => $agreement_between_agreements]);
    try {
      $conclusion->save();
    } catch (EntityStorageException $e) {
      $this->logger->error('Could not create Conclusion. Error: ' . $e);
      throw $e;
    }
  }

}
