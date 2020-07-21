#!/usr/bin/env bash
SERVER=192.168.1.2
USER=test
PASS=wachtwoord

pip install redis hiredis wget --user --upgrade

cd "$(dirname "$0")"
for f in *.pid; do
  if [ -f "$f" ]; then
    echo "Found running processes; please execute stop.sh first!"
    exit 1
  fi
done

cp -f cert.pem ../
nohup python -u action_consumer.py --server "$SERVER" --username "$USER" --password "$PASS" > action_consumer.log 2>&1 & echo $! > action_consumer.pid
nohup python -u audio_consumer.py --server "$SERVER" --username "$USER" --password "$PASS" > audio_consumer.log 2>&1 & echo $! > audio_consumer.pid
nohup python -u tablet_consumer.py --server "$SERVER" --username "$USER" --password "$PASS" > tablet_consumer.log 2>&1 & echo $! > tablet_consumer.pid
nohup python -u video_producer.py --server "$SERVER" --username "$USER" --password "$PASS" > video_producer.log 2>&1 & echo $! > video_producer.pid
nohup python -u event_producer.py --server "$SERVER" --username "$USER" --password "$PASS" > event_producer.log 2>&1 & echo $! > event_producer.pid
nohup python -u audio_producer.py --server "$SERVER" --username "$USER" --password "$PASS" > audio_producer.log 2>&1 & echo $! > audio_producer.pid
