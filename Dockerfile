# Use the official PHP image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy all files
COPY . .

# Expose the port Render uses
EXPOSE 10000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]