class NotificationPage
  include PageObject

  div(:content, id: 'content')
  a(:content) do |page|
    page.content_element.p.a
  end
  # a(:return_to_main_link, text:"Return to Main Page")
end
