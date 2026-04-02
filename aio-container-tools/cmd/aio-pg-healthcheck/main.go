package main

import (
	"context"
	"flag"
	"fmt"
	"os"
	"strconv"

	"github.com/jackc/pgx/v5"
	"github.com/nextcloud/aio-container-tools/internal/util"
)

// tryConnect opens a TCP connection to the given database host:port and runs SELECT 1.
// Returns nil on success, an error otherwise.
func tryConnect(ctx context.Context, host string, port uint16, user, password, database string) error {
	util.Debugf("attempting connection: host=%s port=%d user=%s database=%s", host, port, user, database)

	cfg, err := pgx.ParseConfig("")
	if err != nil {
		return err
	}
	cfg.Host = host
	cfg.Port = port
	cfg.User = user
	cfg.Password = password
	cfg.Database = database

	conn, err := pgx.ConnectConfig(ctx, cfg)
	if err != nil {
		util.Debugf("connection failed: %v", err)
		return err
	}
	defer conn.Close(ctx)

	util.Debugf("connection established, running SELECT 1")
	var result string
	if err := conn.QueryRow(ctx, "SELECT 1").Scan(&result); err != nil {
		util.Debugf("SELECT 1 failed: %v", err)
		return err
	}
	util.Debugf("SELECT 1 returned %q", result)
	return nil
}

// envOrDefault returns the value of the named environment variable,
// or the provided default if the variable is unset or empty.
func envOrDefault(key, defaultVal string) string {
	if v := os.Getenv(key); v != "" {
		util.Debugf("env %s = %q", key, v)
		return v
	}
	util.Debugf("env %s not set, using default %q", key, defaultVal)
	return defaultVal
}

func main() {
	debug := flag.Bool("debug", false, "enable debug output")
	flag.Parse()
	util.SetDebug(*debug)

	util.Debugf("reading required environment variables")
	pgUser := util.RequireEnv("POSTGRES_USER")
	pgPassword := util.RequireEnv("POSTGRES_PASSWORD")
	pgDB := util.RequireEnv("POSTGRES_DB")

	ctx := context.Background()

	pgHost := envOrDefault("POSTGRES_HOST", "127.0.0.1")

	var pgPort uint16 = 5432
	if portStr := os.Getenv("POSTGRES_PORT"); portStr != "" {
		util.Debugf("env POSTGRES_PORT = %q", portStr)
		p, err := strconv.ParseUint(portStr, 10, 16)
		if err != nil {
			fmt.Fprintf(os.Stderr, "invalid POSTGRES_PORT %q: %v\n", portStr, err)
			os.Exit(1)
		}
		pgPort = uint16(p)
	} else {
		util.Debugf("env POSTGRES_PORT not set, using default port %d", pgPort)
	}

	util.Debugf("connecting to: host=%s port=%d user=%s", pgHost, pgPort, pgUser)
	if err := tryConnect(ctx, pgHost, pgPort, pgUser, pgPassword, pgDB); err == nil {
		util.Debugf("connection succeeded, exiting 0")
		os.Exit(0)
	}

	util.Debugf("connection failed, exiting 1")
	os.Exit(1)
}
