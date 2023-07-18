The PHP scripts in this directory are one-off scripts that were meant to be
run once to generate the CSS files needed for Parsoid's Cite HTML. They are
not polished scripts by any means and may not be maintained and updated in
the future. They exist here just in case some 3rd party wikis need them and
may benefit from them.

Retaining the scripts in git also preserves a history of how the CSS files
were generated and how we went about the task of switching over to Parsoid
read views. As such, there is some overall limited value to retaining these
scripts here for now (2023). In a few years, perhaps these could be purged
from the repository.

These scripts need input JSON files, but CI whines about the JSON files and
their formatting. If we can figure out a way to mollify CI, we could perhaps
check in the JSON files as well for completeness.
