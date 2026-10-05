FROM node:24-alpine AS build
WORKDIR /app
RUN npm install -g pnpm@11.22.0
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
ENV VITE_VERSION=3.0.2 VITE_BASE_URL=/ VITE_API_URL=/
RUN pnpm exec vite build && pnpm exec vue-tsc --noEmit

FROM caddy:2.10-alpine
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv
