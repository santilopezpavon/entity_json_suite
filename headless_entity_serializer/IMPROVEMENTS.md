# Improvements

1. **Reduce JSON weight**: Avoid including unnecessary properties (internalDrupal metadata, empty fields, etc.) to minimize file size.
2. **Alias strategy**: Evaluate the current bucket-based alias approach vs. alternatives (flat files, single index file).
3. **Streaming/chunked export**: For large entity sets, consider chunked generation to reduce memory usage.
4. **Webhook/triggers**: Option to trigger incremental updates on entity save via Drupal hooks.
