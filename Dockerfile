# AI Log Inspector: MCP server + demo client
#   docker compose run --rm demo                          # full MCP demo (OpenAI)
#   docker run -i --rm -e OPENAI_API_KEY ai-log-inspector  # MCP server on STDIO for Claude Code / Cursor
FROM php:8.4-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY src ./src
COPY bin ./bin
COPY examples/incident-demo ./examples/incident-demo
RUN composer dump-autoload --optimize --no-dev

ENV LOG_INSPECTOR_PATHS=/app/examples/incident-demo/logs
ENTRYPOINT ["php", "bin/log-inspector-mcp"]
