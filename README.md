# AI Indexing Study

Compare the automatic indexing AI "robot" against human indexers.

DO NOT INSTALL THIS MODULE ALONE. A recipe exists to deploy this module
with necessary Drupal configurations. See Installation, below.


A "Study" involves:
* [Study manager] Loads "documents" as XML from Ovid, via Feeds.
* [Study manager] Assigns reviewers, 2 distinct per document, chosen at random,
to each document
* [Reviewer] goes through their assigned documents, assigning relevant subjects
(as concepts not necessarily MeSH terms)
* [Study Manager] (ideally collaboratively) goes through documents with two completed subject
analyses, creating a consensus list of subjects from the two reviews
* [Study Manager] goes through documents with consensus, and describes
the agreement (or not) between the consensus and the official subject headings.
* [Any participant] view, filter, and download results.


# Installation

* Install the recipe, rosiel/indexing_study_recipe.
