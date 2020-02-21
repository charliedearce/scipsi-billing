Imports System.Data
Imports System.Data.SqlClient
Module Sqlcon
    Public myCon = New SqlConnection(System.Configuration.ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
    Public myCmd As SqlCommand
    Public myReader As SqlDataReader
    Public myAdapter As SqlDataAdapter
    Public result As String
End Module
