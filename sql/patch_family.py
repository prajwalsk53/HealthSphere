
content = open('c:/xampp/htdocs/HealthSphere/patient/medical-records.php','r',encoding='utf-8').read()
start = content.find('    <!-- Family History — Full Interactive Tree -->')
end   = content.find('    <?php endif; ?>', start) + len('    <?php endif; ?>')
print(f'Replacing lines {content[:start].count(chr(10))+1} to {content[:end].count(chr(10))+1}')

new_block = open('c:/xampp/htdocs/HealthSphere/sql/family_tree_block.php','r',encoding='utf-8').read()
content = content[:start] + new_block + content[end:]
open('c:/xampp/htdocs/HealthSphere/patient/medical-records.php','w',encoding='utf-8').write(content)
print('Done, file length:', len(content))
