class SpecialHistoryPage < ArticlePage
  include PageObject

  page_url 'Special:History'

  div(:content_header_bar, css: '.content-header')
  a(:content_header_bar_link) do |page|
    page.content_header_bar_element.link_element(index: 0)
  end

  ul(:side_list, css: '.side-list', index: 0)
  li(:last_contribution) do |page|
    page.side_list_element.list_item_element(index: 0)
  end
  a(:last_contribution_link) do |page|
    page.last_contribution_element.link_element(index: 0)
  end
  h3(:last_contribution_title) do |page|
    page.last_contribution_element.h3_element(index: 0)
  end
  p(:last_contribution_timestamp) do |page|
    page.last_contribution_element.paragraph_element(index: 0, css: '.timestamp')
  end
  p(:last_contribution_edit_summary) do |page|
    page.last_contribution_element.paragraph_element(index: 0, css: '.edit-summary')
  end
  p(:last_contribution_username) do |page|
    page.last_contribution_element.paragraph_element(index: 0, css: '.mw-mf-user')
  end
  a(:more_link, css: '.more')
end

class SpecialContributionsPage < SpecialHistoryPage
  page_url 'Special:Contributions/<%= params[:user] %>'
end
