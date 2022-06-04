class CreateArticlePage
  include PageObject

  page_url '<%=params[:article_name]%>'

  a(:doesnotexist_msg, text: 'Look for pages within Wikipedia that link to this title')
end
