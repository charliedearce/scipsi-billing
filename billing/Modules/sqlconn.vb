Imports System.Data.SqlClient
Imports System.Configuration



Module sqlconn

    Dim connString As String = GetConnectionString(cString)
    Dim conn As New sqlconnection(connString)


End Module
