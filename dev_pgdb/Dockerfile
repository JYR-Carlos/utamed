FROM postgres:17.7-alpine

# Variables de entorno por defecto
ENV POSTGRES_DB=utamed
ENV POSTGRES_USER=utamed
ENV POSTGRES_PASSWORD=utamed_dev_password

# Puerto por defecto
EXPOSE 5432

# Volumen para datos persistentes
VOLUME ["/var/lib/postgresql/data"]

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
  CMD pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}
