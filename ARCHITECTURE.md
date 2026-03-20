# Architecture: laminas-session

## Purpose
A PHP session management library with support for namespaced containers, pluggable save handlers (database, cache, MongoDB), session validation (CSRF, remote addr, user agent), and seamless laminas-servicemanager integration.

## Directory Structure
```
src/
  Session_Manager.php            # Manages PHP session lifecycle: start, write, destroy, regenerate
  Manager_Interface.php          # Contract for session managers
  Container.php                  # Namespaced session data container (ArrayObject-backed)
  Abstract_Container.php         # Base implementation for containers
  Config/
    Session_Config.php           # Full php.ini-based session configuration
    Standard_Config.php          # Simplified configuration
    Config_Interface.php
  Storage/
    Session_Storage.php          # Wraps $_SESSION superglobal
    Array_Storage.php            # In-memory storage (useful for testing)
    Abstract_Session_Array_Storage.php
    Storage_Interface.php
  SaveHandler/
    Save_Handler_Interface.php   # SessionHandlerInterface extension
    Cache.php                    # Stores sessions in any PSR-6 cache pool
    Db_Table_Gateway.php         # Stores sessions in a database table
    Mongo_DB.php                 # Stores sessions in MongoDB
  Validator/
    Validator_Interface.php      # isValid(): bool — called on session start
    Remote_Addr.php              # Rejects sessions if client IP changes
    Http_User_Agent.php          # Rejects sessions if user agent changes
    Csrf.php                     # CSRF token validator
    Id.php                       # Validates session ID format
  Validator_Chain.php            # Runs all registered validators; stops on first failure
  Exception/                     # Typed exceptions
  Service/                       # laminas-servicemanager factories
  Module.php / Config_Provider.php
```

## Key Design Decisions
- **Namespaced containers** — `Container` wraps session data in a PHP namespace (e.g., `Zend_Auth`) preventing key collisions between components sharing a single session.
- **Validator chain on start** — validators run when the session is started. If any validator fails, the session is destroyed and regenerated, preventing session fixation and hijacking.
- **Pluggable save handlers** — any `SessionHandlerInterface` implementation (database, cache, MongoDB) can be registered, giving full control over where session data is stored.
- **Config object** — all `session_*` PHP ini settings are managed through a typed `Session_Config` object rather than direct `ini_set()` calls.

## Extension Points
- Implement `Save_Handler_Interface` (extends PHP's `SessionHandlerInterface`) for custom storage backends.
- Implement `Validator_Interface` for custom session integrity checks.
- Add multiple `Container` instances with different namespaces within one session.

## Dependency Flow
```
Session_Manager::start()
  ├─ Config → apply session_* ini settings
  ├─ Storage → wraps $_SESSION
  ├─ SaveHandler (if set) → registers with session_set_save_handler()
  ├─ Validator_Chain → run validators
  └─ session_start()

Container::offsetGet($key)
  └─ Storage[$namespace][$key]
```
