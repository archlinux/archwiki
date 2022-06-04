class LanguagePage
  include PageObject

  text_field(:search_box_placeholder, placeholder: 'Search language')
  p(:number_languages, text: /This page is available in (\d+) languages/)
  a(:language_search_results, lang: 'es')
end
