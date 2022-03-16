# Probably from this file: https://github.com/Cisco-Talos/clamav/blob/main/Dockerfile
FROM clamav/clamav:0.104.2-2

RUN echo -e '\n\
MaxDirectoryRecursion 30\n\
MaxFileSize 100M\n\
PCREMaxFileSize 100M\n\
StreamMaxLength 100M\
' >> /clamav/etc/clamav/clamd.conf
