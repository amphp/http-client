#!/usr/bin/env bash
openssl genrsa -out amphp.org.key 2048

openssl req -new -key amphp.org.key -out amphp.org.csr -subj "/C=DE/ST=Germany/O=amphp/CN=amphp.org"
openssl x509 -req -days 365 -in amphp.org.csr -signkey amphp.org.key -out amphp.org.crt
cat amphp.org.crt amphp.org.key > amphp.org.pem
rm amphp.org.csr
