#!/bin/bash

gnupghome="$1"
server_pub_key="$2"
domain="$3"

export GNUPGHOME=$gnupghome
gpg --batch --passphrase '' --quick-generate-key "$domain" rsa4096 cert 0
gpg --export -a $domain > $server_pub_key