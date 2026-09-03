import json
with open(r'C:\Users\metha\.gemini\antigravity-ide\brain\c4b88514-f313-4a78-b8d3-f3635fa76652\.system_generated\logs\transcript_full.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if 'mobile-sidebar-overlay' in line:
            d = json.loads(line)
            for call in d.get('tool_calls', []):
                if call.get('name') in ['multi_replace_file_content', 'replace_file_content'] or call.get('name') == 'default_api:multi_replace_file_content' or call.get('name') == 'default_api:replace_file_content':
                    args = call.get('args', {}) if 'args' in call else call.get('arguments', {})
                    if isinstance(args, str): args = json.loads(args)
                    chunks = args.get('ReplacementChunks', [args])
                    for chunk in chunks:
                        if 'mobile-sidebar-overlay' in chunk.get('ReplacementContent', ''):
                            with open('scratch/recover2.txt', 'w', encoding='utf-8') as out:
                                out.write(chunk['ReplacementContent'])
                            print('Recovered')
                            break
