# logs.py
from datetime import datetime
import sys

def _now():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")

def log_info(message):
    print(f"[{_now()}] [INFO] {message}", file=sys.stdout, flush=True)

def log_warn(message):
    print(f"[{_now()}] [WARN] {message}", file=sys.stdout, flush=True)

def log_error(message):
    print(f"[{_now()}] [ERROR] {message}", file=sys.stderr, flush=True)
