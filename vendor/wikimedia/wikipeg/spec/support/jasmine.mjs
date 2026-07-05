export default {
  spec_dir: "tests/jasmine",
  spec_files: [
    "**/*[sS]pec.?(m)js"
  ],
  helpers: [
    "**/helpers.?(m)js"
  ],
  env: {
    stopSpecOnExpectationFailure: false,
    random: false,
    forbidDuplicateNames: false
  }
}
