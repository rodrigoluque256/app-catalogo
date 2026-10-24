-- Base separada para la suite de Pest (phpunit.xml fuerza DB_DATABASE=catalogo_test).
CREATE DATABASE catalogo_test OWNER catalogo;

-- Base separada para el test de navegador (servicios del perfil "e2e", ver bin/e2e.sh).
CREATE DATABASE catalogo_e2e OWNER catalogo;
