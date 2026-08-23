# -*- coding: utf-8 -*-
from pathlib import Path
import re

ar = Path(r"c:\bmcapital\araclar")
for html in ar.glob("*.html"):
    text = html.read_text(encoding="utf-8")
    text2 = re.sub(
        r'<div class="tools-aside-list".*?>.*?</div>',
        f'<div class="tools-aside-list" data-tools-aside="{html.name}"></div>',
        text,
        count=1,
        flags=re.DOTALL,
    )
    text2 = text2.replace("araclar.js?v=3", "araclar.js?v=5").replace("araclar.js?v=4", "araclar.js?v=5")
    text2 = text2.replace("style.css?v=araclar3", "style.css?v=araclar5").replace(
        "style.css?v=araclar4", "style.css?v=araclar5"
    )
    if text2 != text:
        html.write_text(text2, encoding="utf-8")
        print("ok", html.name)
    else:
        print("same", html.name)
