class SearchPage < ArticlePage
  include PageObject

  ul(:list_of_results, css: '.mw-search-results')
end
