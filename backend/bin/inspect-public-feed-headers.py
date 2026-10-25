#!/usr/bin/env python3
"""Read only the first CSV line of official CKAN datasets; no record values."""
import csv
import io
import json
import urllib.request
from collections import Counter
from urllib.error import HTTPError

SOURCES = {
    "udruga": "https://data.gov.hr/ckan/dataset/b04f3da4-d2b6-4789-9e5f-dc4cc18ae6fe/resource/535a5b96-9f28-409a-ae99-e245964c16c9/download/data.csv",
    "zaklada": "https://data.gov.hr/ckan/dataset/5ed3fa2b-774f-4c4a-9332-80e8486614bd/resource/16429c50-062b-4381-b624-a849dbc80c12/download/data.csv",
}

for label, url in SOURCES.items():
    try:
        request = urllib.request.Request(url, headers={"User-Agent": "TVRTKE-Registry-Header-Audit/1.0"})
        with urllib.request.urlopen(request, timeout=20) as response:
            line = response.readline(16384)
            if not line or b"\n" not in line:
                raise ValueError("CSV header missing or exceeds 16 KB")
            try:
                text = line.decode("utf-8-sig")
            except UnicodeDecodeError:
                text = line.decode("cp1250")
            candidate = csv.Sniffer().sniff(text, delimiters=",;\t") if any(x in text for x in (",", ";", "\t")) else csv.excel
            header = next(csv.reader(io.StringIO(text), dialect=candidate))
            print(json.dumps({"source": label, "status": response.status,
                              "content_type": response.headers.get("Content-Type"),
                              "delimiter": candidate.delimiter, "header": header},
                             ensure_ascii=False))
    except Exception as error:
        print(json.dumps({"source":label, "error":type(error).__name__, "detail":str(error)[:180]}))

# Aggregated schema validation only: never print any record or personal values.
for label, source in SOURCES.items():
    try:
        request = urllib.request.Request(source,headers={"User-Agent":"TVRTKE-Registry-Header-Audit/1.0"})
        with urllib.request.urlopen(request,timeout=45) as response:
            wrapper = io.TextIOWrapper(response,encoding="utf-8-sig",newline="")
            reader = csv.DictReader(wrapper)
            status = Counter()
            date_patterns = Counter()
            maxlen = Counter()
            blank = Counter()
            rows = 0
            expected = {"REGISTARSKI_BROJ","NAZIV","STATUS","ZUPANIJA","SJEDISTE",
                        "DATUM_UPISA","DATUM_STATUSA","DATUM_OSNIVACKE_SKUPSTINE",
                        "OIB_ZAKLADE", "OPIS_DJELATNOSTI"}
            for row in reader:
                rows += 1
                if rows > 150000:
                    break
                status[(row.get("STATUS") or "").strip()[:80]] += 1
                for key in expected:
                    val = (row.get(key) or "").strip()
                    maxlen[key] = max(maxlen[key], len(val))
                    if not val:
                        blank[key] += 1
                for key in ("DATUM_UPISA","DATUM_STATUSA","DATUM_OSNIVACKE_SKUPSTINE"):
                    val=(row.get(key) or "").strip()
                    # Mask every digit to avoid exposing a real record's date.
                    pattern="".join("9" if c.isdigit() else c for c in val)[:65] if val else "(empty)"
                    date_patterns[key+"|"+pattern]+=1
            print(json.dumps({"source":label,"rows_inspected":rows,
                "statuses":dict(status),"max_field_length":dict(maxlen),
                "blank_counts":dict(blank),"masked_date_shapes":dict(date_patterns)},
                ensure_ascii=False))
    except Exception as error:
        print(json.dumps({"source":label,"aggregate_error":type(error).__name__}))
