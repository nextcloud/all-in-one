package main

import (
	"context"
	"flag"
	"fmt"
	"strings"

	"github.com/jackc/pgx/v5"
	"github.com/nextcloud/aio-container-tools/internal/util"
)

// quoteLiteral safely quotes a string as a PostgreSQL string literal.
// Single quotes are escaped by doubling them. This is safe with
// standard_conforming_strings=on (default since PostgreSQL 9.1).
func quoteLiteral(s string) string {
	return "'" + strings.ReplaceAll(s, "'", "''") + "'"
}

// main reimplements init-user-db.sh:
//   - Creates $POSTGRES_DB_OWNER (falling back to $POSTGRES_USER) with $POSTGRES_PASSWORD and CREATEDB
//   - Transfers ownership of $POSTGRES_DB to that user
//   - Grants all privileges on the database and public schema
//   - Connects using $POSTGRES_USER in all cases
func main() {
	debug := flag.Bool("debug", false, "enable debug output")
	flag.Parse()
	util.SetDebug(*debug)

	util.Debugf("reading required environment variables")
	pgUser := util.RequireEnv("POSTGRES_USER")
	pgPassword := util.RequireEnv("POSTGRES_PASSWORD")
	pgDB := util.RequireEnv("POSTGRES_DB")
	pgDBOwner := util.OptionalEnv("POSTGRES_DB_OWNER", pgUser)

	util.Debugf("building connection config: host=/var/run/postgresql port=5432 user=%s database=%s", pgUser, pgDB)
	cfg, err := pgx.ParseConfig("")
	if err != nil {
		util.ErrorOut(fmt.Errorf("building connection config: %w", err))
	}
	cfg.Host = "/var/run/postgresql"
	cfg.Port = 5432
	cfg.User = pgUser
	cfg.Password = pgPassword
	cfg.Database = pgDB

	ctx := context.Background()
	util.Debugf("connecting to postgres via unix socket")
	conn, err := pgx.ConnectConfig(ctx, cfg)
	if err != nil {
		util.ErrorOut(fmt.Errorf("connecting to postgres: %w", err))
	}
	defer conn.Close(ctx)
	util.Debugf("connected successfully")

	dbOwner := pgDBOwner
	util.Debugf("dbOwner = %q (from POSTGRES_DB_OWNER=%q, POSTGRES_USER=%q)", dbOwner, pgDBOwner, pgUser)
	// pgx.Identifier.Sanitize() double-quotes and escapes the identifier safely.
	dbOwnerIdent := pgx.Identifier{dbOwner}.Sanitize()
	dbIdent := pgx.Identifier{pgDB}.Sanitize()
	util.Debugf("quoted dbOwnerIdent = %s, dbIdent = %s", dbOwnerIdent, dbIdent)

	statements := []string{
		fmt.Sprintf("CREATE USER %s WITH PASSWORD %s CREATEDB", dbOwnerIdent, quoteLiteral(pgPassword)),
		fmt.Sprintf("ALTER DATABASE %s OWNER TO %s", dbIdent, dbOwnerIdent),
		fmt.Sprintf("GRANT ALL PRIVILEGES ON DATABASE %s TO %s", dbIdent, dbOwnerIdent),
		fmt.Sprintf("GRANT ALL PRIVILEGES ON SCHEMA public TO %s", dbOwnerIdent),
	}

	for i, stmt := range statements {
		util.Debugf("executing statement %d/%d: %s", i+1, len(statements), stmt)
		if _, err := conn.Exec(ctx, stmt); err != nil {
			util.ErrorOut(fmt.Errorf("executing statement: %w", err))
		}
		util.Debugf("statement %d/%d succeeded", i+1, len(statements))
	}
	util.Debugf("all statements executed successfully")
}
