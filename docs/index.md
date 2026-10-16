---
name: Diff

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: data
  icon: switch
  tags: diff compare array-diff text-diff unified-diff patch myers change-detection audit-log json html
  description: Semantic diffs of PHP arrays and objects, with paths, identity-aware lists, moves and redaction, plus GitHub-style text diffs with hunks, unified patches and HTML.

seo:
  title: "{package.fullName} - Structured Array Diffs & GitHub-Style Text Diffs"
  description: "{package.name} is a PHP package that reports what changed between two values: added, removed, modified and moved data at exact paths, records matched by ID, redacted secrets, and line diffs with hunks, unified patches and HTML. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: php diff, php array diff, compare arrays php, structured diff php, php text diff, unified diff php, myers diff php, php audit log changes, json diff php, html diff php, change detection php
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a PHP package that reports what changed between two values, at exact paths, with identity-aware lists, moves and redaction, plus GitHub-style text diffs with hunks, unified patches and HTML."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
