## 1. Reproduce

- [x] 1.1 Observe failing slow-upload and safe-feedback JavaScript tests.
- [x] 1.2 Reproduce the original browser failure with an eight-second delayed queue response.

## 2. Fix and verify

- [x] 2.1 Apply an upload-only timeout and distinct safe failure messages.
- [x] 2.2 Verify real jobs-table insertion and atomic cleanup when insertion fails, without queue fakes.
- [x] 2.3 Pass JavaScript/PHP regression tests, build, and delayed-response browser validation.
- [x] 2.4 Update deployment guidance, synchronize the specification, and archive verified work.
