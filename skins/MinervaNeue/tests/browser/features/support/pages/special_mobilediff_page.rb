class SpecialMobileDiffPage < ArticlePage
  include PageObject

  div(:user_info, id: 'mw-mf-userinfo')
end
