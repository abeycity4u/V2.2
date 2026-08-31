from pathlib import Path
import re

p = Path('management/poultry_ruminant_report.php')
text = p.read_text()
# Remove both the valid legacy form and the malformed V2.2.41 form that left
# PrintManager.print();); behind and made the button non-clickable.
text = re.sub(r"\s*\$\('#printBtn'\)\.on\('click',\s*\(\)\s*=>\s*PrintManager\.print\(\);?\)?;", '', text)
text = text.replace("$('#printBtn').on('click', () => PrintManager.print(););", '')
p.write_text(text)
print('Removed legacy Poultry & Ruminant browser-print handler.')
