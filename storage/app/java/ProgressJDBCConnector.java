import java.sql.*;
import java.util.Properties;
import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonArray;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Map;

public class ProgressJDBCConnector {

    private String jdbcUrl;
    private String username;
    private String password;
    private Connection connection;

    public ProgressJDBCConnector(String jdbcUrl, String username, String password) {
        this.jdbcUrl = jdbcUrl;
        this.username = username;
        this.password = password;
    }

    /**
     * Testa a conexao com o banco Progress
     */
    public String testConnection() {
        JsonObject result = new JsonObject();

        try {
            // Carregar driver JDBC
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");

            // Estabelecer conexao
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);

            connection = DriverManager.getConnection(jdbcUrl, props);

            // Testar conexao - sem consulta complexa
            String timestamp = new java.util.Date().toString();

            result.addProperty("success", true);
            result.addProperty("message", "Conexao Progress JDBC estabelecida com sucesso");

            JsonObject data = new JsonObject();
            data.addProperty("host", extractHost(jdbcUrl));
            data.addProperty("database", extractDatabase(jdbcUrl));
            data.addProperty("timestamp", timestamp);
            data.addProperty("jdbc_url", jdbcUrl);
            result.add("data", data);

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro na conexao Progress JDBC: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    /**
     * Executa SELECT na tabela transporte
     */
    public String getTransportes(String whereClause, int limit) {
        JsonObject result = new JsonObject();

        try {
            // Carregar driver e conectar
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);
            connection = DriverManager.getConnection(jdbcUrl, props);

            // Construir query simples para Progress
            StringBuilder sql = new StringBuilder("SELECT nomtrn, codtrn FROM PUB.transporte");

            // Adicionar filtros se fornecidos
            if (whereClause != null && !whereClause.trim().isEmpty()) {
                sql.append(" WHERE ").append(whereClause);
            }

            // Progress pode ter sintaxe especifica para ORDER BY, removendo por enquanto

            Statement stmt = connection.createStatement();
            ResultSet rs = stmt.executeQuery(sql.toString());

            // Converter ResultSet para JSON com limitacao
            JsonArray transportes = new JsonArray();
            ResultSetMetaData metaData = rs.getMetaData();
            int columnCount = metaData.getColumnCount();
            int count = 0;

            while (rs.next() && count < limit) {
                JsonObject row = new JsonObject();
                for (int i = 1; i <= columnCount; i++) {
                    String columnName = metaData.getColumnName(i).toLowerCase();
                    Object value = rs.getObject(i);

                    if (value == null) {
                        row.add(columnName, null);
                    } else if (value instanceof String) {
                        row.addProperty(columnName, (String) value);
                    } else if (value instanceof Number) {
                        row.addProperty(columnName, (Number) value);
                    } else if (value instanceof Boolean) {
                        row.addProperty(columnName, (Boolean) value);
                    } else {
                        row.addProperty(columnName, value.toString());
                    }
                }
                transportes.add(row);
                count++;
            }

            result.addProperty("success", true);
            result.addProperty("message", "Dados da tabela transporte obtidos com sucesso");

            JsonObject data = new JsonObject();
            data.add("transportes", transportes);
            data.addProperty("total", transportes.size());
            data.addProperty("sql_executed", sql.toString());
            result.add("data", data);

            rs.close();
            stmt.close();

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro na consulta Progress JDBC: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    /**
     * Executa SELECT paginado na tabela transporte
     */
    public String getTransportesPaginated(String whereClause, int limit, int offset) {
        JsonObject result = new JsonObject();

        try {
            // Carregar driver e conectar
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);
            connection = DriverManager.getConnection(jdbcUrl, props);

            // Construir query paginada usando Progress TOP syntax
            StringBuilder sql = new StringBuilder();
            sql.append("SELECT TOP ").append(limit).append(" codtrn, nomtrn FROM PUB.transporte");

            // Adicionar filtros se fornecidos
            if (whereClause != null && !whereClause.trim().isEmpty()) {
                sql.append(" ").append(whereClause);
            }

            // Progress nao suporte OFFSET diretamente, simulamos com condicoes WHERE
            if (offset > 0) {
                String offsetCondition = whereClause.isEmpty() ? " WHERE " : " AND ";
                sql.append(offsetCondition).append("codtrn > (SELECT MAX(codtrn) FROM (SELECT TOP ")
                   .append(offset).append(" codtrn FROM PUB.transporte");

                if (whereClause != null && !whereClause.trim().isEmpty()) {
                    sql.append(" ").append(whereClause);
                }

                sql.append(" ORDER BY codtrn) sub)");
            }

            // Ordenar resultados
            sql.append(" ORDER BY codtrn");

            Statement stmt = connection.createStatement();
            ResultSet rs = stmt.executeQuery(sql.toString());

            // Converter ResultSet para JSON
            JsonArray transportes = new JsonArray();
            ResultSetMetaData metaData = rs.getMetaData();
            int columnCount = metaData.getColumnCount();

            while (rs.next()) {
                JsonObject row = new JsonObject();
                for (int i = 1; i <= columnCount; i++) {
                    String columnName = metaData.getColumnName(i).toLowerCase();
                    Object value = rs.getObject(i);

                    if (value == null) {
                        row.add(columnName, null);
                    } else if (value instanceof String) {
                        row.addProperty(columnName, (String) value);
                    } else if (value instanceof Number) {
                        row.addProperty(columnName, (Number) value);
                    } else if (value instanceof Boolean) {
                        row.addProperty(columnName, (Boolean) value);
                    } else {
                        row.addProperty(columnName, value.toString());
                    }
                }
                transportes.add(row);
            }

            result.addProperty("success", true);
            result.addProperty("message", "Dados paginados da tabela transporte obtidos com sucesso");

            JsonObject data = new JsonObject();
            data.add("results", transportes);
            data.addProperty("count", transportes.size());
            data.addProperty("sql_executed", sql.toString());
            data.addProperty("limit", limit);
            data.addProperty("offset", offset);
            result.add("data", data);

            rs.close();
            stmt.close();

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro na consulta paginada Progress JDBC: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    /**
     * Executa consulta SQL customizada
     */
    public String executeCustomQuery(String sql) {
        JsonObject result = new JsonObject();

        try {
            // Validacao basica de seguranca
            String sqlUpper = sql.toUpperCase().trim();
            if (!sqlUpper.startsWith("SELECT")) {
                result.addProperty("success", false);
                result.addProperty("error", "Apenas consultas SELECT sao permitidas");
                return result.toString();
            }

            // Carregar driver e conectar
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);
            connection = DriverManager.getConnection(jdbcUrl, props);

            Statement stmt = connection.createStatement();
            ResultSet rs = stmt.executeQuery(sql);

            // Converter ResultSet para JSON
            JsonArray results = new JsonArray();
            ResultSetMetaData metaData = rs.getMetaData();
            int columnCount = metaData.getColumnCount();

            while (rs.next()) {
                JsonObject row = new JsonObject();
                for (int i = 1; i <= columnCount; i++) {
                    String columnName = metaData.getColumnName(i).toLowerCase();
                    Object value = rs.getObject(i);

                    if (value == null) {
                        row.add(columnName, null);
                    } else if (value instanceof String) {
                        row.addProperty(columnName, (String) value);
                    } else if (value instanceof Number) {
                        row.addProperty(columnName, (Number) value);
                    } else if (value instanceof Boolean) {
                        row.addProperty(columnName, (Boolean) value);
                    } else {
                        row.addProperty(columnName, value.toString());
                    }
                }
                results.add(row);
            }

            result.addProperty("success", true);
            result.addProperty("message", "Consulta executada com sucesso");

            JsonObject data = new JsonObject();
            data.add("results", results);
            data.addProperty("total", results.size());
            data.addProperty("sql", sql);
            result.add("data", data);

            rs.close();
            stmt.close();

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro na execucao da consulta: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    /**
     * Executa UPDATE, INSERT ou DELETE
     */
    public String executeUpdate(String sql) {
        JsonObject result = new JsonObject();

        try {
            // Validacao basica de seguranca - permitir apenas UPDATE, INSERT, DELETE
            String sqlUpper = sql.toUpperCase().trim();
            if (!sqlUpper.startsWith("UPDATE") && !sqlUpper.startsWith("INSERT") && !sqlUpper.startsWith("DELETE")) {
                result.addProperty("success", false);
                result.addProperty("error", "Apenas comandos UPDATE, INSERT e DELETE sao permitidos");
                return result.toString();
            }

            // Carregar driver e conectar
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);
            connection = DriverManager.getConnection(jdbcUrl, props);

            Statement stmt = connection.createStatement();
            int affectedRows = stmt.executeUpdate(sql);

            result.addProperty("success", true);
            result.addProperty("message", "Comando executado com sucesso");

            JsonObject data = new JsonObject();
            data.addProperty("affected_rows", affectedRows);
            data.addProperty("sql", sql);
            result.add("data", data);

            stmt.close();

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro na execucao do comando: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    /**
     * Obtem o schema/estrutura de uma tabela especifica
     */
    public String getTableSchema(String tableName) {
        JsonObject result = new JsonObject();

        try {
            // Carregar driver e conectar
            Class.forName("com.ddtek.jdbc.openedge.OpenEdgeDriver");
            Properties props = new Properties();
            props.setProperty("user", username);
            props.setProperty("password", password);
            connection = DriverManager.getConnection(jdbcUrl, props);

            // Usar DatabaseMetaData para obter informacoes da tabela
            DatabaseMetaData metaData = connection.getMetaData();

            // Obter informacoes das colunas
            ResultSet columns = metaData.getColumns(null, "PUB", tableName.toUpperCase(), null);

            JsonArray columnsArray = new JsonArray();
            Map<String, String> columnTypes = new HashMap<>();

            while (columns.next()) {
                JsonObject column = new JsonObject();

                String columnName = columns.getString("COLUMN_NAME");
                String dataType = columns.getString("TYPE_NAME");
                int columnSize = columns.getInt("COLUMN_SIZE");
                int decimalDigits = columns.getInt("DECIMAL_DIGITS");
                boolean nullable = columns.getBoolean("NULLABLE");
                String defaultValue = columns.getString("COLUMN_DEF");

                column.addProperty("name", columnName.toLowerCase());
                column.addProperty("type", dataType);
                column.addProperty("size", columnSize);
                column.addProperty("decimal_digits", decimalDigits);
                column.addProperty("nullable", nullable);
                column.addProperty("default_value", defaultValue);

                columnTypes.put(columnName.toLowerCase(), dataType);
                columnsArray.add(column);
            }
            columns.close();

            // Obter informacoes das chaves primarias
            ResultSet primaryKeys = metaData.getPrimaryKeys(null, "PUB", tableName.toUpperCase());
            JsonArray primaryKeysArray = new JsonArray();

            while (primaryKeys.next()) {
                JsonObject pk = new JsonObject();
                pk.addProperty("column_name", primaryKeys.getString("COLUMN_NAME").toLowerCase());
                pk.addProperty("key_seq", primaryKeys.getInt("KEY_SEQ"));
                pk.addProperty("pk_name", primaryKeys.getString("PK_NAME"));
                primaryKeysArray.add(pk);
            }
            primaryKeys.close();

            // Obter informacoes dos indices
            ResultSet indexes = metaData.getIndexInfo(null, "PUB", tableName.toUpperCase(), false, false);
            JsonArray indexesArray = new JsonArray();
            Map<String, JsonObject> indexMap = new HashMap<>();

            while (indexes.next()) {
                String indexName = indexes.getString("INDEX_NAME");
                if (indexName != null) {
                    JsonObject index = indexMap.get(indexName);
                    if (index == null) {
                        index = new JsonObject();
                        index.addProperty("name", indexName);
                        index.addProperty("unique", !indexes.getBoolean("NON_UNIQUE"));
                        index.add("columns", new JsonArray());
                        indexMap.put(indexName, index);
                    }

                    JsonObject indexColumn = new JsonObject();
                    indexColumn.addProperty("column_name", indexes.getString("COLUMN_NAME").toLowerCase());
                    indexColumn.addProperty("ordinal_position", indexes.getInt("ORDINAL_POSITION"));
                    indexColumn.addProperty("asc_or_desc", indexes.getString("ASC_OR_DESC"));

                    index.getAsJsonArray("columns").add(indexColumn);
                }
            }

            for (JsonObject index : indexMap.values()) {
                indexesArray.add(index);
            }
            indexes.close();

            // Tentar obter uma amostra dos dados para analise adicional
            JsonArray sampleData = new JsonArray();
            try {
                Statement stmt = connection.createStatement();
                ResultSet rs = stmt.executeQuery("SELECT TOP 3 * FROM PUB." + tableName);
                ResultSetMetaData rsMetaData = rs.getMetaData();
                int columnCount = rsMetaData.getColumnCount();

                while (rs.next()) {
                    JsonObject row = new JsonObject();
                    for (int i = 1; i <= columnCount; i++) {
                        String columnName = rsMetaData.getColumnName(i).toLowerCase();
                        Object value = rs.getObject(i);

                        if (value == null) {
                            row.add(columnName, null);
                        } else if (value instanceof String) {
                            String strValue = (String) value;
                            // Limitar tamanho da string para evitar dados muito grandes
                            if (strValue.length() > 100) {
                                strValue = strValue.substring(0, 100) + "...";
                            }
                            row.addProperty(columnName, strValue);
                        } else if (value instanceof Number) {
                            row.addProperty(columnName, (Number) value);
                        } else if (value instanceof Boolean) {
                            row.addProperty(columnName, (Boolean) value);
                        } else {
                            String strValue = value.toString();
                            if (strValue.length() > 100) {
                                strValue = strValue.substring(0, 100) + "...";
                            }
                            row.addProperty(columnName, strValue);
                        }
                    }
                    sampleData.add(row);
                }
                rs.close();
                stmt.close();
            } catch (SQLException e) {
                // Se falhar ao obter dados de amostra, continuar sem eles
                System.err.println("Aviso: Nao foi possivel obter dados de amostra: " + e.getMessage());
            }

            result.addProperty("success", true);
            result.addProperty("message", "Schema da tabela obtido com sucesso");

            JsonObject data = new JsonObject();
            data.addProperty("table_name", tableName.toLowerCase());
            data.add("columns", columnsArray);
            data.add("primary_keys", primaryKeysArray);
            data.add("indexes", indexesArray);
            data.add("sample_data", sampleData);
            data.addProperty("column_count", columnsArray.size());
            data.addProperty("primary_key_count", primaryKeysArray.size());
            data.addProperty("index_count", indexesArray.size());
            result.add("data", data);

        } catch (ClassNotFoundException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Driver JDBC Progress nao encontrado: " + e.getMessage());
        } catch (SQLException e) {
            result.addProperty("success", false);
            result.addProperty("error", "Erro ao obter schema da tabela: " + e.getMessage());
        } finally {
            closeConnection();
        }

        return result.toString();
    }

    private void closeConnection() {
        try {
            if (connection != null && !connection.isClosed()) {
                connection.close();
            }
        } catch (SQLException e) {
            System.err.println("Erro ao fechar conexao: " + e.getMessage());
        }
    }

    private String extractHost(String jdbcUrl) {
        try {
            return jdbcUrl.split("://")[1].split(":")[0];
        } catch (Exception e) {
            return "unknown";
        }
    }

    private String extractDatabase(String jdbcUrl) {
        try {
            String[] parts = jdbcUrl.split("databaseName=");
            if (parts.length > 1) {
                return parts[1].split(";")[0];
            }
            return "unknown";
        } catch (Exception e) {
            return "unknown";
        }
    }

    public static void main(String[] args) {
        if (args.length < 4) {
            System.out.println("{\"success\":false,\"error\":\"Argumentos insuficientes. Uso: java ProgressJDBCConnector <action> <jdbcUrl> <username> <password> [params...]\"}");
            return;
        }

        String action = args[0];
        String jdbcUrl = args[1];
        String username = args[2];
        String password = args[3];

        ProgressJDBCConnector connector = new ProgressJDBCConnector(jdbcUrl, username, password);

        switch (action) {
            case "test":
                System.out.println(connector.testConnection());
                break;
            case "transportes":
                String whereClause = args.length > 4 && !args[4].isEmpty() ? args[4] : "";
                int limit = args.length > 5 ? Integer.parseInt(args[5]) : 100;
                System.out.println(connector.getTransportes(whereClause, limit));
                break;
            case "query-paginated":
                String whereClausePag = args.length > 4 && !args[4].isEmpty() ? args[4] : "";
                int limitPag = args.length > 5 ? Integer.parseInt(args[5]) : 10;
                int offsetPag = args.length > 6 ? Integer.parseInt(args[6]) : 0;
                System.out.println(connector.getTransportesPaginated(whereClausePag, limitPag, offsetPag));
                break;
            case "query":
                String sql = args.length > 4 ? args[4] : "";
                System.out.println(connector.executeCustomQuery(sql));
                break;
            case "update":
                String updateSql = args.length > 4 ? args[4] : "";
                System.out.println(connector.executeUpdate(updateSql));
                break;
            case "schema":
                String tableName = args.length > 4 ? args[4] : "transporte";
                System.out.println(connector.getTableSchema(tableName));
                break;
            default:
                System.out.println("{\"success\":false,\"error\":\"Acao invalida. Use: test, transportes, query-paginated, query, update, ou schema\"}");
        }
    }
}
