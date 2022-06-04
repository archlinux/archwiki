class DiffPage
  include PageObject

  element(:inserted_content, 'ins')
  element(:deleted_content, 'del')
end
