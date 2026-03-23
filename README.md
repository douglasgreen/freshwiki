# FreshWiki

This wiki is in development.

* Uses PHP/MySQL.
* The wiki consists of nodes, pages, tags, and files.
* The nodes are a hierarchy of numbered categories with names, like c=1 "General" and c=2 "Help". Help is a subcategory (child node) of General in this example so its full display name is "General/Help".
* The pages are numbered wiki pages with names, like p=1 "Home Page". Each page must a leaf under one and only one node.
* The tags are named (not numbered) tags with all lower case and hyphen, like "wiki-help". Tagged pages are listed automatically on their tag page /tags/
* The files are media files like images and video that are numbered with names, like f=1 "logo.png".
* No redirects are provided because the nodes, pages, and files are numbered as permanent URLs. The tags are labeled as a page attribute in an associated tags table, so changing their name doesn't break the association.
* A set of command-line scripts will be provided to manage nodes and import/export pages in batches.
* Categories and pages are listed in order of most recent edits at the top.
* Pages have a status flag that makes them easier to archive. Archiving them takes them out of browsing and normal search unless "search archived pages" is checked during search. Pages have a status of "fresh" which is a timestamp indicating the last time they were completely reviewed and confirmed.
* Most wikis just offer manual indexing and search. FreshWiki offers browsing of hierarchies as the main mode of navigation.
* Markdown is the language.
* Users are numbered like u=1 with a username.
* User pages go into the Users/<username> top-level node.
* Category names should be (but are not enforced to be) title case.
* Report pages show the most viewed, most linked, and most edited pages.
* Only one copy of a page is saved per day to prevent database bloat. If a user edits a page 100 times in a day, each edit will be logged, but only the end result will be saved.
* A report shows a summary of the change log for each page that day.
* Editing the node structure is provided as a single big page to edit.
* Blogs can go in a Blogs/<blog name> node since posts (pages) are automatically in date order with most recent at top.
* Each page has a name, summary and text. The summary is a single paragraph of text up to 255 characters with no Markdown that is shown in browsing.
* Each page also has a comments section below it that allows people to comment or ask questions. The comments are a properly threaded discussion.
* Each user has a watch page that shows any activity on pages they have watched including edits and comments.
* There is a "find similar pages" feature that shows a list of pages ranked by similarity. This helps to remove duplicates.
* In multiuser mode, admins are designated who must accept or reject each edit before it's accepted. Edits by admins don't require approval. Only one user may edit a page at one time.

## Problems this solves

* The permanent URLs avoid having special characters in URLs and needing redirects to reorganize.
* The node system solves the problem of wiki disorganization by having a hierarchy for everything.
* The content stagnation problem is solved by pushing recent content to the top and making it easy to archive pages while still keeping them around.

## Discussion

The levels are who, what, when, where, and how. You can skip level levels, but you can’t put them in a different order. Who is who it’s done for or who’s doing it. What is the project, area, or resource. When is the year. Where is the physical and virtual location that work is done. And how was the method that you using, like the type of work. this is similar to the PARA method, where projects are short-term goals, areas are long-term goals, resources are referenced material, and archives are archived. So the real question is how to split apart what area. PAR is what and archived is a status.
