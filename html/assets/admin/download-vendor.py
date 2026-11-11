"""Download pinned frontend dependencies. Run from the project root if restoring assets."""
import concurrent.futures
import pathlib
import re
import urllib.request

root = pathlib.Path(__file__).parent / 'vendor'
assets = {
 'bootstrap.min.css': 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
 'bootstrap.bundle.min.js': 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
 'bootstrap-icons.min.css': 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
 'fonts/bootstrap-icons.woff2': 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2',
 'fonts/bootstrap-icons.woff': 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff',
 'jquery.min.js': 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
 'apexcharts.min.js': 'https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js',
 'dataTables.bootstrap5.min.css': 'https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css',
 'dataTables.min.js': 'https://cdn.datatables.net/2.1.8/js/dataTables.min.js',
 'dataTables.bootstrap5.min.js': 'https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js',
 'select2.min.css': 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css',
 'select2.min.js': 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js',
 'licenses/bootstrap.txt': 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/LICENSE',
 'licenses/bootstrap-icons.txt': 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/LICENSE',
 'licenses/jquery.txt': 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/LICENSE.txt',
 'licenses/apexcharts.txt': 'https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/LICENSE',
 'licenses/datatables.txt': 'https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/License.txt',
 'licenses/select2.txt': 'https://cdn.jsdelivr.net/npm/select2@4.0.13/LICENSE.md',
 'licenses/inter.txt': 'https://raw.githubusercontent.com/google/fonts/main/ofl/inter/OFL.txt',
}
def fetch(pair):
 name, url = pair
 target = root / name
 if target.exists():
  return name
 target.parent.mkdir(parents=True, exist_ok=True)
 request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
 with urllib.request.urlopen(request, timeout=45) as response:
  target.write_bytes(response.read())
 return name

if __name__ == '__main__':
 with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
  for name in pool.map(fetch, assets.items()):
   print('Downloaded', name)
 request = urllib.request.Request('https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;550;600;650;700;750;800&display=swap', headers={'User-Agent':'Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36'})
 with urllib.request.urlopen(request,timeout=45) as response:
  stylesheet = response.read().decode()
 blocks = re.findall(r'/\* latin \*/\s*(@font-face\s*\{[^}]+\})', stylesheet)
 if not blocks:
  blocks = re.findall(r'@font-face\s*\{[^}]+\}', stylesheet)
 font_css=[]
 seen={}
 for block in blocks:
  for url in re.findall(r'url\((https://[^)]+)\)', block):
   if url not in seen:
    name='fonts/inter-'+str(len(seen))+'.woff2'
    fetch((name,url))
    seen[url]=name
   block=block.replace(url,seen[url])
  font_css.append(block)
 (root/'inter.css').write_text('\n'.join(font_css),encoding='utf-8')
 print('Downloaded Inter font and stylesheet')
