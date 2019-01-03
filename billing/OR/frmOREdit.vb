Imports System.Data
Imports System.Data.SqlClient
Public Class frmOREdit
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property

    Private Sub frmOREdit_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            Try
                If txthtax.Text = "" Then
                    txthtax.Text = 0
                End If
                Dim total As Decimal
                total = lblamt.Text - txthtax.Text
                txtTotal.Text = total

                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_orbill_trans SET ot_net = '" & txtTotal.Text & "', ot_citw = '" & txthtax.Text & "' WHERE ot_id = '" & txtid.Text & "' and ot_or_no = '" & frmor.txtOrno.Text & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                Me.Dispose()
                viewORItems()
            Catch ex As Exception
                MsgBox("Please enter valid amount.")
            End Try

        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub


    Private Sub frmOREdit_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        With frmor
            lblno.Text = .txtbillno.Text
            lblamt.Text = .txtamt.Text
            txthtax.Text = .txtholdtax.Text
        End With
    End Sub

    Private Sub txthtax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txthtax.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txthtax_TextChanged(sender As Object, e As EventArgs) Handles txthtax.TextChanged
        On Error Resume Next
        If txthtax.Text = "" Then
            txthtax.Text = 0
        End If
    End Sub

    Private Sub txtdisc_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub


End Class