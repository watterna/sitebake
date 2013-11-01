template: page
title: Welcome
list:
  - item 1
  - item 2
  - item 3
---
This is the **index** page

{{ metadata.title|title }}

{{metadata.time}}

{% for item in metadata.list %}
- {{ item }}
{% endfor %}
