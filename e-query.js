const $ = {
    find : (s) => document.querySelector(s),
    all : (s) => Array.from(document.querySelectorAll(s)),
    value : (s) => document.querySelector(s).value
}

module.exports = {
    $
}