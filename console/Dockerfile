FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY server.mjs ./

ENV CONSOLE_PORT=8090
ENV CONSOLE_DEV_STUB=true

EXPOSE 8090

CMD ["npm", "start"]
