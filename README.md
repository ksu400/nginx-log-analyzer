# Nginx Log Analyzer

Веб-приложение на Yii2 для анализа nginx access-логов. Парсит лог-файлы, сохраняет данные в MySQL и отображает статистику на дашборде с графиками и фильтрами.

## Требования

- Docker и Docker Compose

## Быстрый старт

### 1. Клонировать репозиторий

```bash
git clone https://github.com/ksu400/nginx-log-analyzer.git
cd nginx-log-analyzer
```

### 2. Создать файл с переменными окружения

Linux / macOS:
```bash
cp .env.example .env
```

Windows (cmd):
```cmd
copy .env.example .env
```

Отредактируй `.env` — замени пароли на свои и при необходимости измени порты:

| Переменная | По умолчанию | Описание |
|---|---|---|
| `DB_PASS` | — | Пароль пользователя MySQL (**обязателен**, не оставляй пустым) |
| `MYSQL_ROOT_PASSWORD` | — | Пароль root MySQL (**обязателен**, должен совпадать с `DB_PASS`) |
| `COOKIE_VALIDATION_KEY` | — | Случайная строка **минимум 32 символа** (только латиница и цифры) |
| `APP_PORT` | `8080` | Порт приложения (меняй если занят) |
| `DB_HOST` | `db` | Имя сервиса MySQL в Docker-сети |
| `DB_PORT` | `3306` | Порт MySQL внутри Docker-сети |

### 3. Запустить контейнеры

```bash
docker compose up -d --build
```

Запустятся три контейнера:

| Контейнер | Адрес | Описание |
|-----------|-------|----------|
| `app` | http://localhost:8080 | Приложение (порт из `APP_PORT`) |
| `db` | localhost:3307 | MySQL (порт из `DB_PORT`) |
| `phpmyadmin` | http://localhost:8081 | Управление БД |

### 4. Применить миграцию

Создаёт таблицу `logs` в базе данных:

```bash
docker exec nginx-log-analyzer-app-1 php yii migrate --interactive=0
```

### 5. Загрузить логи

Linux / macOS:
```bash
docker cp /путь/к/вашему/access.log nginx-log-analyzer-app-1:/tmp/access.log
docker exec nginx-log-analyzer-app-1 php yii parse-log /tmp/access.log
```

Windows (cmd):
```cmd
docker cp "C:\путь\к\вашему\access.log" nginx-log-analyzer-app-1:/tmp/access.log
docker exec nginx-log-analyzer-app-1 php yii parse-log /tmp/access.log
```

> Если в пути есть пробелы или кириллица — обязательно оборачивай путь в кавычки.

Готово — открывай http://localhost:8080

---

## Консольные команды

### Загрузка логов

Команда принимает **nginx access-лог в combined-формате**. Имя файла может быть любым.

Пример строки лога:
```
192.168.1.1 - - [10/May/2024:12:00:00 +0000] "GET /index.php HTTP/1.1" 200 512 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
```

Linux / macOS:
```bash
# Скопировать файл в контейнер
docker cp /путь/к/файлу/любое_имя.log nginx-log-analyzer-app-1:/tmp/любое_имя.log

# Запустить парсинг
docker exec nginx-log-analyzer-app-1 php yii parse-log /tmp/любое_имя.log

# Изменить размер батча вставки (по умолчанию 500)
docker exec nginx-log-analyzer-app-1 php yii parse-log --batchSize=1000 /tmp/access.log
```

Windows (cmd):
```cmd
docker cp "C:\путь\к\файлу\любое_имя.log" nginx-log-analyzer-app-1:/tmp/любое_имя.log
docker exec nginx-log-analyzer-app-1 php yii parse-log /tmp/любое_имя.log
```

### Очистка данных

Мягкое удаление всех записей — данные физически остаются в БД, но скрываются из приложения.
Позволяет восстановить данные при необходимости.

```bash
docker exec nginx-log-analyzer-app-1 php yii clear-logs
```

---

## Структура базы данных

Таблица `logs`:

| Колонка | Тип | Описание                                      |
|---|---|-----------------------------------------------|
| `id` | int | Первичный ключ                                |
| `ip` | varchar | IP-адрес клиента                              |
| `requested_at` | datetime | Время запроса (UTC)                           |
| `url` | varchar | Запрошенный URL                               |
| `user_agent` | text | Строка User-Agent                             |
| `os` | varchar | Операционная система (из UA)                  |
| `architecture` | varchar | Архитектура процессора (из UA)                |
| `browser` | varchar | Браузер (из UA)                               |
| `extra_fields` | json | Экстарафилды, полная сырая строка лога        |
| `deleted_at` | datetime | Дата мягкого удаления (NULL = запись активна) |

Колонка `extra_fields` хранит оригинальную строку лога без изменений:

```json
{
  "raw": "192.168.1.1 - - [10/May/2024:12:00:00 +0000] \"GET /index.php HTTP/1.1\" 200 512 \"-\" \"Mozilla/5.0...\""
}
```

Это позволяет в будущем извлечь любые данные (статус, метод, байты, referer) без повторной загрузки файлов.

---

## Если порт 8080 занят

Измени `APP_PORT` в `.env`:

```env
APP_PORT=8090
```

Затем перезапусти контейнеры:

```bash
docker compose up -d
```

---

## Управление контейнерами

```bash
# Запустить
docker compose up -d

# Остановить
docker compose down

# Перезапустить приложение после изменения кода
docker compose up -d --build app

# Посмотреть логи приложения
docker compose logs app

# Пересоздать базу данных с нуля (удалит все данные)
docker compose down -v && docker compose up -d
docker exec nginx-log-analyzer-app-1 php yii migrate --interactive=0
```

## Просмотр базы данных

Открой http://localhost:8081 (phpMyAdmin) и войди:
- Сервер: `db`
- Пользователь: `yii2`
- Пароль: значение `DB_PASS` из твоего `.env`
