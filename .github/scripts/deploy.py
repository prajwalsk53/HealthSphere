#!/usr/bin/env python3
import paramiko
import os
import fnmatch
from pathlib import Path

HOSTNAME = 'access-5020406432.webspace-host.com'
PORT     = 22
USERNAME = os.environ['SFTP_USER']
PASSWORD = os.environ['SFTP_PASS']
REMOTE_BASE = '/public/HealthSphere'
LOCAL_BASE  = '.'

EXCLUDE_DIRS  = {'.git', 'logs', 'sql', 'node_modules', '.github', 'flutter_app', 'app'}
EXCLUDE_FILES = {'mail.php', 'deploy_key', 'deploy_key.pub', 'authorized_keys'}
EXCLUDE_EXT   = {'.sql'}

def is_excluded(rel_path):
    parts = Path(rel_path).parts
    for part in parts[:-1]:
        if part in EXCLUDE_DIRS:
            return True
    filename = parts[-1]
    if filename in EXCLUDE_FILES:
        return True
    if Path(filename).suffix in EXCLUDE_EXT:
        return True
    return False

def mkdir_p(sftp, remote_path):
    parts = remote_path.lstrip('/').split('/')
    current = ''
    for part in parts:
        current += '/' + part
        try:
            sftp.stat(current)
        except FileNotFoundError:
            sftp.mkdir(current)

print(f'Connecting to {HOSTNAME}:{PORT} as {USERNAME}...')
transport = paramiko.Transport((HOSTNAME, PORT))
transport.connect(username=USERNAME, password=PASSWORD)
sftp = paramiko.SFTPClient.from_transport(transport)
print('Connected.')

uploaded = 0
for root, dirs, files in os.walk(LOCAL_BASE):
    dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
    for filename in files:
        local_path  = os.path.join(root, filename)
        rel_path    = os.path.relpath(local_path, LOCAL_BASE).replace('\\', '/')
        if is_excluded(rel_path):
            print(f'  skip: {rel_path}')
            continue
        remote_path = REMOTE_BASE + '/' + rel_path
        remote_dir  = '/'.join(remote_path.split('/')[:-1])
        mkdir_p(sftp, remote_dir)
        print(f'  upload: {rel_path}')
        sftp.put(local_path, remote_path)
        uploaded += 1

sftp.close()
transport.close()
print(f'\nDone. {uploaded} files uploaded.')
