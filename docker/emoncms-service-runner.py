#!/usr/bin/env python3
# Keep in sync with emoncms-docker web/service-runner.py — mount until image rebuild.
# Uses REDIS_HOST / REDIS_PORT (docker default redis:6379); upstream redis.Redis() used localhost.

import os
import subprocess
import time
import shlex
import redis

KEYS = ["service-runner", "emoncms:service-runner"]


def _redis_client():
    return redis.Redis(
        host=os.environ.get("REDIS_HOST", "127.0.0.1"),
        port=int(os.environ.get("REDIS_PORT", "6379")),
    )


def connect_redis():
    while True:
        try:
            server = _redis_client()
            if server.ping():
                print("Connected to redis server", flush=True)
                return server
        except redis.exceptions.ConnectionError:
            print("Unable to connect to redis server, sleeping for 30s", flush=True)
        time.sleep(30)


def main():
    print("Starting service-runner", flush=True)
    server = connect_redis()
    while True:
        try:
            packed = server.blpop(KEYS)
            if not packed:
                continue
            flag = packed[1].decode()
        except redis.exceptions.ConnectionError:
            print("Connection to redis server lost, attempting to reconnect", flush=True)
            server = connect_redis()
            continue

        print("Got flag:", flag, flush=True)
        if ">" in flag:
            script, logfile = flag.split(">")
            print("STARTING:", script, '&>', logfile, flush=True)
            with open(logfile, "w") as f:
                try:
                    subprocess.call(shlex.split(script), stdout=f, stderr=f)
                except Exception as exc:
                    f.write("Error running [%s]" % script)
                    f.write("Exception occurred: %s" % exc)
                    continue
        else:
            script = flag
            print("STARTING:", script, flush=True)
            try:
                subprocess.call(shlex.split(script), stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT)
            except Exception as exc:
                continue

        print("COMPLETE:", script, flush=True)


if __name__ == "__main__":
    main()
