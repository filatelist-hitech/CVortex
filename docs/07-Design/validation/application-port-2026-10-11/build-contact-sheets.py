"""Contact sheets are inspection aids; raw source PNGs are never modified."""
import json, pathlib
from PIL import Image, ImageDraw
here = pathlib.Path(__file__).resolve().parent
report = json.loads((here / 'comparison-report.json').read_text())
for width in [1440, 768, 390, 320]:
    rows = [row for row in report['results'] if row['width'] == width]
    for scope in ['viewport', 'scroll']:
        images = [dict(state=row['state'], **row['images']) for row in rows] if scope == 'viewport' else [dict(state=row['state'] + ' / scroll ' + str(i + 1), **tile) for row in rows for i, tile in enumerate(row['scrollComparisons'])]
        if not images: continue
        sheet = Image.new('RGB', (1320, ((len(images) + 1) // 2) * 184), '#172039')
        draw = ImageDraw.Draw(sheet)
        for i, row in enumerate(images):
            for j, kind in enumerate(['canonical', 'production', 'difference']):
                x, y = (i % 2) * 660 + j * 220, (i // 2) * 184
                im = Image.open(here / row[kind]).convert('RGB')
                im.thumbnail((216, 158))
                sheet.paste(im, (x + (216 - im.width) // 2, y + 22))
                draw.text((x + 3, y + 4), row['state'] + ' / ' + kind, fill='white')
        sheet.save(here / (str(width) + '-' + scope + '-contact.png'))
print('Contact sheets generated from final report; raw PNGs retained')
