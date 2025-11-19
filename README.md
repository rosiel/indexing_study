# AI Indexing Study

Compare the automatic indexing AI "robot" against human indexers.

A "Study" involves:
* [Study Manager] Loads "documents" as XML from Ovid, via Feeds.
* [Study Manager] Assigns reviewers, 2 distinct per document, chosen at random,
to each document
* [Reviewer] goes through their assigned documents, assigning relevant subjects
(as concepts not necessarily MeSH terms)
* [?Heightened permission?] goes through documents with two completed subject
analyses, creating a consensus list of subjects from the two reviews
* [?heightened permission?] goes through documents with consensus, and describes
the agreement (or not) between the consensus and the official subject headings.
* [?anyone?] view, filter, and download results.


# Instructions

* Install this module via a recipe which will set up the site [to come]
