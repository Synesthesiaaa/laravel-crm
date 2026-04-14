import re
import pathlib

root = pathlib.Path("resources/views/admin")
for path in sorted(root.glob("*.blade.php")):
    if path.name.startswith("inline-"):
        continue
    text = path.read_text(encoding="utf-8")
    m = re.search(r"@section\('content'\)\s*(.*)\s*@endsection", text, re.S)
    if not m:
        print("skip", path)
        continue
    body = m.group(1).strip()
    out = root / ("inline-" + path.name)
    out.write_text(body + "\n", encoding="utf-8")
    print("ok", out.name, len(body))
