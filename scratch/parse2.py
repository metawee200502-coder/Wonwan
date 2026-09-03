import json
with open('scratch/raw.txt', 'r', encoding='utf-8') as f:
    s = f.read()
    i = s.find('{')
    d = json.loads(s[i:])
    for call in d['tool_calls']:
        for chunk in call['args']['ReplacementChunks']:
            if 'mobile-sidebar-overlay' in chunk['ReplacementContent']:
                with open('scratch/recover2.txt', 'w', encoding='utf-8') as out:
                    out.write(chunk['ReplacementContent'])
                print('Success')
