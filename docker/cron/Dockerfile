# Render Cron Job (služba project-name-cron v render.yaml): zavolá endpoint /cron webu, viz docs/Architecture/cron.md.
FROM alpine:3

RUN apk add --no-cache curl ca-certificates

COPY run.sh /usr/local/bin/cron-run
RUN chmod 0755 /usr/local/bin/cron-run

CMD ["cron-run"]
