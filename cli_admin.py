import psycopg2
import sys

# Konfiguracja połączenia z bazą danych
DB_CONFIG = {
    "dbname": "hotel_db",
    "user": "postgres",
    "password": "1234",  # Hasło zgodne z konfiguracją db.php
    "host": "localhost",
    "port": "5432"
}


def system_rezerwacji_cli(uid, pid, data_od, data_do, dorosli, dzieci):
    """
    Funkcja interfejsowa do wywoływania logiki biznesowej PL/pgSQL.
    """
    conn = None
    try:
        # 1. Nawiązanie połączenia z silnikiem bazy danych
        print(f"[SYSTEM] Łączenie z bazą danych {DB_CONFIG['dbname']}...")
        conn = psycopg2.connect(**DB_CONFIG)
        cur = conn.cursor()

        # 2. Przygotowanie parametrów dla procedury składowanej
        print(f"[SYSTEM] Próba rezerwacji: User={uid}, Pokój={pid}, Termin={data_od} do {data_do}")

        # 3. Wywołanie procedury 'dokonaj_rezerwacji'
        # To tutaj dzieje się "magia" - Python zleca pracę bazie danych.
        # Odpowiednik SQL: SELECT dokonaj_rezerwacji(...)
        cur.callproc('dokonaj_rezerwacji', [uid, pid, data_od, data_do, dorosli, dzieci])

        # 4. Zatwierdzenie transakcji (COMMIT)
        # Bez tego kroku zmiany nie zostałyby zapisane trwale.
        conn.commit()

        # 5. Pobranie wyniku (Komunikat tekstowy z funkcji PL/pgSQL)
        result = cur.fetchone()
        print(f"\n[SUKCES] Odpowiedź z bazy danych:\n >> {result[0]}")

    except psycopg2.DatabaseError as e:
        # Obsługa błędów SQL (np. zajęty termin, błąd klucza obcego)
        if conn:
            conn.rollback()  # Wycofanie zmian w razie błędu
        print(f"\n[BŁĄD KRYTYCZNY] Operacja odrzucona przez serwer SQL:\n >> {e}")

    except Exception as e:
        print(f"[BŁĄD APLIKACJI] {e}")

    finally:
        # Zamknięcie połączenia
        if conn:
            cur.close()
            conn.close()
            print("\n[SYSTEM] Połączenie zamknięte.")


# Punkt wejścia programu (Entry Point)
if __name__ == "__main__":
    print("--- HOTEL GRAND - ADMIN CLI TOOL v1.0 ---")
    # Przykładowe wywołanie "na sztywno" dla celów demonstracyjnych
    # Parametry: UserID=1, RoomID=2, Od, Do, Dorośli=2, Dzieci=0
    system_rezerwacji_cli(1, 2, '2025-08-01', '2025-08-05', 2, 0)