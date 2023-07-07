# Indexing Something something

This module is for comparing the completeness of automated indexing of scholarly journal articles (or similarly structured publications). It provides a smooth interface for human indexers to apply subject terms and then to compare their terms against those already assigned to the article. It provides mechanisms for divying a pool of articles among a number of human indexers, and for the indexers to smoothly proceed with assessing their assigned articles.


# Instructions

* Install this module.

## References

Each journal article is represented as a Reference (a type provided by the BibCite module).  There are many types of references available from that module, but the "Journal Article" type of reference is the one that out-of-the box is configured to be in Pools (see below). It would be easiest to import all references as "Journal Articles". 

Add references by importing data at Content > Bibliography > References > Import (admin/content/bibcite/reference/import). I found the most reliable way to import reference data is to transform it to RIS with the following fields populated:

```
TY  - JOUR
TI  - Reference Title
T2  - Journal Title
AB  - Abstract
KW  - MESH Headings
PY  - Pub year (required by bibcite)
AN  - Reference Identifier
ER  - 
```

A script is (included) that transforms Medline exported XML into this format, or you can use other methods.


## Pools

A pool is a set of references (journal articles) to undergo human-robot indexing comparison.
In order to be analyzed, references must be added to Pools. This can be done at /indexing/references. 

See the list of pools at /indexing/pools. Create a new pool there.

Pools are a type of Drupal entity called a Storage Entity. You can create a new pool at Content > Storage Data > Add a Storage Entity > Pool. 
Create a new pool a 


## Assignment



## Response


display mode indexing study
display mode keyword
display mode table

displaymode citation (for reference)


