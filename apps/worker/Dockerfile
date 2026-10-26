FROM python:3.11-slim-bookworm

# Install system utilities for Bun and CLI workers
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Bun runtime for portal search skills
RUN curl -fsSL https://bun.sh/install | bash
ENV PATH="/root/.bun/bin:${PATH}"

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

CMD ["python3", "-c", "import time; print('CVortex Worker runtime ready.'); time.sleep(86400)"]
