FROM python:3.11-slim-bookworm

# Install system utilities for Bun and CLI workers
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Bun runtime for portal search skills
RUN curl -fsSL https://bun.sh/install | bash

# Create unprivileged system user cvortex and make Bun accessible globally
RUN useradd -u 1000 -m -s /bin/bash cvortex \
    && cp /root/.bun/bin/bun /usr/local/bin/bun \
    && chmod 755 /usr/local/bin/bun

RUN mkdir -p /app && chown -R cvortex:cvortex /app
WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY --chown=cvortex:cvortex . .

USER cvortex

CMD ["python3", "-c", "import signal, sys; signal.signal(signal.SIGTERM, lambda *_: sys.exit(0)); signal.pause()"]
