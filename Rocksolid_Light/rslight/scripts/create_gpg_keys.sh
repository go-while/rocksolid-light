#!/bin/bash

gnupghome="$1"
server_pub_key="$2"
fingerprint="$3"
domain="$4"

export GNUPGHOME=$gnupghome

gpg --batch --passphrase '' --quick-gen-key $domain default default 0
gpg --export -a $domain > $server_pub_key
gpg --fingerprint $domain | sed '2!d' > $fingerprint