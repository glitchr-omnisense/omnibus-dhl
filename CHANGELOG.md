# Changelog

## [Unreleased]

### Fixed
- `Label::$url` is `null`: it held the `trackingUrl` of DHL's booking answer, a MyDHL API address,
  not a document to download; the label comes inline in `content`.
