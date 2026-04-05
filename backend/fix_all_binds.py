import os

api_dir = '/Users/maheshpatra/Desktop/edducare-dashboard/dashboard/backend/api'
count = 0

for root, _, files in os.walk(api_dir):
    for filename in files:
        if filename.endswith('.php'):
            filepath = os.path.join(root, filename)
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Blindly replace ALL bindParam with bindValue
            new_content = content.replace('->bindParam(', '->bindValue(')
            
            if new_content != content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f'Fixed {filepath}')
                count += 1

print(f'Done! Completely fixed {count} files by replacing all bindParam with bindValue.')
